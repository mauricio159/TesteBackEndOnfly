-- Roda uma única vez, quando o volume do MySQL é criado.
-- Cria um banco SÓ para os testes automatizados, assim rodar os testes
-- nunca apaga os dados que você está usando para demonstrar o sistema.
CREATE DATABASE IF NOT EXISTS viagens_corporativas_teste
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON viagens_corporativas_teste.* TO 'viagens'@'%';
FLUSH PRIVILEGES;
