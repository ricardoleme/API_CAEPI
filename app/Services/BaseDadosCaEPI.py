import csv
import ftplib
import io
import zipfile
import pandas as pd
import os
import re

class BaseDadosCaEPI:
    baseDadosDF = None 
    nomeArquivoBase = 'tgg_export_caepi.txt'
    nomeArquivoConfigNomesColunas = 'config_nomes_colunas.csv'
    nomeArquivoErros = 'CAs_com_erros.txt'    
    urlBase = 'ftp.mtps.gov.br'
    caminho = 'portal/fiscalizacao/seguranca-e-saude-no-trabalho/caepi/'
    nColunas = 19


    nomeColunas = [
        "RegistroCA",
        "DataValidade",
        "Situacao",
        "NRProcesso",
        "CNPJ",
        "RazaoSocial",
        "Natureza",
        "NomeEquipamento",
        "DescricaoEquipamento",
        "MarcaCA",
        "Referencia",
        "Cor",
        "AprovadoParaLaudo",
        "RestricaoLaudo",
        "ObservacaoAnaliseLaudo",
        "CNPJLaboratorio",
        "RazaoSocialLaboratorio",
        "NRLaudo",
        "Norma"        
    ]

    def __init__(self):
        self = self

    def _baixarArquivoBaseCaEPI(self):
        # Nome do arquivo ZIP remoto
        nomeArquivoZip = 'tgg_export_caepi.zip'
        
        # 1. Remover arquivo local, se existir
        if os.path.exists(self.nomeArquivoBase):
            os.remove(self.nomeArquivoBase)

        # 2. Conexão e Navegação
        try:
            ftp = ftplib.FTP(self.urlBase)
            ftp.login() # Login anônimo
            ftp.cwd(self.caminho)
            
            # 3. VERIFICAÇÃO DE EXISTÊNCIA
            # Retorna uma lista dos arquivos no diretório atual
            lista_arquivos = ftp.nlst() 
            
            if nomeArquivoZip not in lista_arquivos:
                print(f"❌ Erro: O arquivo **{nomeArquivoZip}** não foi encontrado no diretório **{self.caminho}**.")
                # Levanta um erro ou retorna para interromper o processo
                raise FileNotFoundError(f"Arquivo {nomeArquivoZip} não encontrado no FTP.") 

            # 4. Download
            r = io.BytesIO()
            print(f"✅ Arquivo {nomeArquivoZip} encontrado. Iniciando download...")
            ftp.retrbinary(f'RETR {nomeArquivoZip}', r.write)
            ftp.quit() # Fechar a conexão
            
            # 5. Extração
            arquivoZip = zipfile.ZipFile(r)
            arquivoZip.extractall()
            
            print("Download e extração concluídos.")
            
        except ftplib.all_errors as e:
            # Captura erros de FTP (conexão, permissão, etc.)
            print(f"🚨 Erro durante a conexão ou operação FTP: {e}")
            raise 
        except FileNotFoundError:
            # Re-lança o erro de arquivo não encontrado
            raise
        except Exception as e:
            # Captura outros erros, como problemas na extração do ZIP
            print(f"🚨 Ocorreu um erro inesperado: {e}")
            raise
    
    def _transformarEmDataFrame(self):          
        listaCas = self._retornarCAsSemErros()
        cols = listaCas[0]
        self.baseDadosDF = pd.DataFrame(listaCas, columns=cols)        

        self.baseDadosDF.columns = self.__retornaNomesColunas()

    def __retornaNomesColunas(self):
        arquivo = open(self.nomeArquivoConfigNomesColunas, encoding='UTF-8')

        return arquivo.readline().split(',')

    def _retornarCAsSemErros(self) -> list:
        listaCAsValidos = []
        listaCAsInvalidos = []

        with open(self.nomeArquivoBase, encoding='UTF-8') as arquivo:
            reader = csv.reader(arquivo, delimiter='|', quotechar='"')
            
            for linhaDf in reader:
                if len(linhaDf) > self.nColunas:
                    # Reconstrói a linha original para tratamento
                    linha_original = '|'.join(linhaDf)
                    resul_tratamento = self._tratarCasComErros(linha_original)
                    if resul_tratamento['sucess']:
                        linhaDf = resul_tratamento['linha']
                    else:
                        listaCAsInvalidos.append(linha_original)
                        continue

                listaCAsValidos.append(linhaDf)

        if listaCAsInvalidos:
            self._criarArquivoComErros(listaCAsInvalidos)

        return listaCAsValidos
    
    def _tratarCasComErros(self, linha) -> dict:
        linhaDf = re.split(r'(?<! )\|', linha)
        if len(linhaDf) > self.nColunas: # Erro
            return {
                'sucess': False,
                'linha': linha
            }

        return    {
            'sucess': True,
            'linha': linhaDf
        }

    def _criarArquivoComErros(self, listaCAsInvalidos:list) -> None:
        with open(self.nomeArquivoErros, 'w') as f:
            f.writelines(listaCAsInvalidos)
    
    def retornarBaseDados(self) -> pd.DataFrame:
        if not os.path.exists(self.nomeArquivoBase):
            print("Aguarde o download...")        
            self._baixarArquivoBaseCaEPI()
            print(f"Download concluido!")

        self._transformarEmDataFrame()
        return self.baseDadosDF