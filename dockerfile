# Usa versão estável e compatível de Python
FROM python:3.11-slim

# Define diretório de trabalho
WORKDIR /app

# Copia apenas o requirements.txt inicialmente (para cache eficiente)
COPY requirements.txt .

# Atualiza pip e instala dependências
RUN pip install --no-cache-dir --upgrade pip setuptools wheel \
    && pip install --no-cache-dir -r requirements.txt

# Copia os demais arquivos do projeto
COPY . .

# Render define a variável PORT automaticamente (ex: 10000)
# O comando abaixo garante compatibilidade
CMD exec uvicorn main:app --host 0.0.0.0 --port ${PORT:-8000}
