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
        nomeArquivoZip = 'tgg_export_caepi.zip'
        
        # 1. Remover arquivo local, se existir
        if os.path.exists(self.nomeArquivoBase):
            os.remove(self.nomeArquivoBase)

        try:
            # 2. Conexão e Navegação
            ftp = ftplib.FTP(self.urlBase)
            ftp.login() # Login anônimo
            ftp.cwd(self.caminho)
            ftp.set_pasv(True)
            
            # 3. VERIFICAÇÃO DE EXISTÊNCIA (nlst)
            lista_arquivos = ftp.nlst() 
            
            if nomeArquivoZip not in lista_arquivos:
                print(f"❌ Erro: O arquivo **{nomeArquivoZip}** não foi encontrado no diretório **{self.caminho}**.")
                # Lança exceção para interromper o processo
                raise FileNotFoundError(f"Arquivo {nomeArquivoZip} não encontrado no FTP.") 

            # 4. Download
            r = io.BytesIO()
            print(f"✅ Arquivo {nomeArquivoZip} encontrado. Iniciando download...")
            ftp.retrbinary(f'RETR {nomeArquivoZip}', r.write)
            ftp.quit() # Fechar a conexão
            
            # 5. VERIFICAÇÃO DE DOWNLOAD VAZIO (resolve BadZipFile)
            if r.tell() == 0:
                print("🚨 Erro: O download do arquivo ZIP resultou em um arquivo vazio (0 bytes).")
                raise IOError("Download do arquivo ZIP vazio. Verifique permissões ou conexão.")
            
            # Volta o ponteiro para o início do buffer para leitura do ZIP
            r.seek(0)

            # 6. Extração
            arquivoZip = zipfile.ZipFile(r)
            arquivoZip.extractall()
            
            print("Download e extração concluídos.")
            
        except ftplib.all_errors as e:
            print(f"🚨 Erro durante a conexão ou operação FTP: {e}")
            raise 
        except FileNotFoundError:
            # Re-lança o erro de arquivo não encontrado
            raise
        except zipfile.BadZipFile as e:
            print(f"🚨 Erro de ZIP: O conteúdo baixado não é um arquivo ZIP válido. Motivo: {e}")
            raise
        except Exception as e:
            print(f"🚨 Ocorreu um erro inesperado: {e}")
            raise
    
    def _transformarEmDataFrame(self):          
        listaCas = self._retornarCAsSemErros()
        
        # Garante que a lista não está vazia (ocorreria se o download falhasse sem exceção)
        if not listaCas:
            raise Exception("Não foi possível carregar os dados. A lista de CAs está vazia.")
            
        cols = listaCas[0]
        self.baseDadosDF = pd.DataFrame(listaCas, columns=cols)        

        self.baseDadosDF.columns = self.__retornaNomesColunas()

    def __retornaNomesColunas(self):
        # Abre o arquivo de configuração de nomes de colunas
        # É importante garantir que este arquivo exista no deploy
        if not os.path.exists(self.nomeArquivoConfigNomesColunas):
             raise FileNotFoundError(f"Arquivo de configuração {self.nomeArquivoConfigNomesColunas} não encontrado.")
             
        arquivo = open(self.nomeArquivoConfigNomesColunas, encoding='UTF-8')

        return arquivo.readline().split(',')

    def _retornarCAsSemErros(self) -> list:
        listaCAsValidos = []
        listaCAsInvalidos = []
        
        # Verifica se o arquivo base existe localmente antes de tentar ler
        if not os.path.exists(self.nomeArquivoBase):
            # Se a função retornarBaseDados foi chamada corretamente, este arquivo deve existir
            raise FileNotFoundError(f"Arquivo base {self.nomeArquivoBase} não encontrado após o download/extração.")

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
