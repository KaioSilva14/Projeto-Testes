# Organiza++

O **Organiza++** é um gerenciador de tarefas com uma interface web. O servidor foi desenvolvido em **C++**, enquanto a interface utiliza **HTML, CSS e JavaScript**.

O sistema permite criar, concluir, pesquisar, filtrar e excluir tarefas. As tarefas são salvas automaticamente em um arquivo JSON.

## Tecnologias utilizadas

- **C++17**: servidor e API do sistema;

- **HTML5**: estrutura da interface;

- **CSS3**: aparência e responsividade;

- **JavaScript**: interação da página com o servidor;

- **JSON**: armazenamento das tarefas;

- **cpp-httplib**: servidor HTTP em C++;

- **nlohmann/json**: leitura e escrita dos dados em JSON.

## Estrutura do projeto

A pasta deve estar organizada desta forma:

```
organiza-plus/
├── main.cpp
├── app.exe                 # criado depois da compilação
├── include/
│   ├── httplib.h
│   └── json.hpp
├── dados/
│   └── tarefas.json
└── public/
    ├── index.html
    ├── style.css
    └── script.js
```

## Pré-requisitos

É necessário ter o compilador **g++** instalado no Windows. Para verificar, abra o terminal do VS Code e execute:

```
g++ --version
```

Se aparecer a versão do compilador, ele está instalado corretamente.

## Abrir o projeto no VS Code

Abra no VS Code a pasta principal do projeto:

```
C:\Users\joaov\OneDrive\Desktop\Testes-Aplicações\organiza-plus
```

Depois abra o terminal integrado usando:

```
Ctrl + `
```

Confirme que o terminal está na pasta correta:

```
pwd
```

O caminho exibido deve terminar com:

```
\organiza-plus
```

## Instalar as bibliotecas

Caso os arquivos ainda não existam, crie a pasta `include`:

```
mkdir include
```

Baixe a biblioteca do servidor C++:

```
curl.exe -fL "https://raw.githubusercontent.com/yhirose/cpp-httplib/master/httplib.h" -o "include/httplib.h"
```

Baixe a biblioteca para trabalhar com JSON:

```
curl.exe -fL "https://raw.githubusercontent.com/nlohmann/json/develop/single_include/nlohmann/json.hpp" -o "include/json.hpp"
```

O parâmetro `-f` faz o comando informar erro caso o arquivo não seja encontrado. Isso evita salvar uma página `404: Not Found` dentro do arquivo da biblioteca.

## Conferir os arquivos

Para conferir os arquivos da pasta `include`, execute:

```
dir include
```

Devem aparecer:

```
httplib.h
json.hpp
```

Para verificar as pastas do projeto, execute:

```
dir
```

Devem aparecer:

```
main.cpp
include
public
dados
```

## Compilar o projeto

Dentro da pasta `organiza-plus`, execute:

```
g++ -std=c++17 -Iinclude main.cpp -o app.exe -lws2_32
```

Explicação do comando:

- `g++`: compilador C++;

- `-std=c++17`: usa o padrão C++17;

- `-Iinclude`: procura as bibliotecas na pasta `include`;

- `main.cpp`: arquivo principal do programa;

- `-o app.exe`: cria o executável com o nome `app.exe`;

- `-lws2_32`: adiciona suporte de rede do Windows.

Se não aparecer uma mensagem de erro, o arquivo `app.exe` foi criado com sucesso.

## Executar o projeto

No mesmo terminal, execute:

```
.\app.exe
```

Na primeira execução, o Windows pode mostrar uma janela perguntando se deseja permitir o acesso do aplicativo à rede. Permita o acesso para que o navegador consiga se conectar ao servidor.

Quando funcionar, o terminal mostrará uma mensagem parecida com:

```
Organiza++ rodando em http://localhost:8080
```

Não feche esse terminal enquanto estiver usando o sistema.

## Abrir no navegador

Abra o navegador e acesse:

```
http://localhost:8080
```

A interface do Organiza++ será carregada.

## Funcionalidades

Na interface, é possível:

1. Criar uma nova tarefa;

1. Adicionar título e descrição;

1. Escolher prioridade baixa, média ou alta;

1. Marcar uma tarefa como concluída;

1. Pesquisar pelo título ou descrição;

1. Filtrar tarefas pendentes;

1. Filtrar tarefas concluídas;

1. Filtrar tarefas de prioridade alta;

1. Excluir tarefas;

1. Visualizar o total, as tarefas pendentes, as concluídas e o progresso.

As tarefas ficam salvas no arquivo:

```
dados\tarefas.json
```

## Executar novamente no futuro

Sempre que quiser abrir o projeto:

1. Abra a pasta `organiza-plus` no VS Code;

1. Abra o terminal;

1. Execute:

```
.\app.exe
```

1. Abra no navegador:

```
http://localhost:8080
```

Não é necessário compilar novamente se o arquivo `main.cpp` não tiver sido alterado.

## Quando for necessário compilar novamente

Se alterar o `main.cpp`, compile outra vez:

```
g++ -std=c++17 -Iinclude main.cpp -o app.exe -lws2_32
```

Depois execute:

```
.\app.exe
```

Se alterar somente `index.html`, `style.css` ou `script.js`, não é necessário recompilar o C++. Basta atualizar a página do navegador usando:

```
Ctrl + R
```

## Encerrar o servidor

Para parar o programa, volte ao terminal onde o servidor está rodando e pressione:

```
Ctrl + C
```

## Solução de problemas

### Erro: `g++ não é reconhecido`

O compilador C++ não está instalado ou não foi adicionado ao PATH do Windows. Instale o MinGW-w64/MSYS2 e reinicie o VS Code.

### Erro: `404: Not Found` dentro de `httplib.h`

O arquivo da biblioteca foi baixado incorretamente. Baixe novamente usando:

```
curl.exe -fL "https://raw.githubusercontent.com/yhirose/cpp-httplib/master/httplib.h" -o "include/httplib.h"
```

### Erro: o Windows bloqueou o `app.exe`

Desbloqueie o arquivo:

```
Unblock-File -Path ".\app.exe"
```

Depois tente executar:

```
.\app.exe
```

Se o Windows mostrar uma janela de permissão de acesso à rede, permita o aplicativo.

### Erro: a porta 8080 já está em uso

Feche o servidor antigo com `Ctrl + C`. Se não conseguir, abra o PowerShell como administrador e execute:

```
netstat -ano | findstr :8080
```

Depois encerre o processo usando o PID exibido:

```
taskkill /PID NUMERO_DO_PID /F
```

### O navegador não abre a página

Verifique se o terminal ainda mostra o servidor em execução. Depois acesse exatamente:

```
http://localhost:8080
```

Não feche o terminal do servidor.

## Comandos principais resumidos

```
cd "C:\Users\joaov\OneDrive\Desktop\Testes-Aplicações\organiza-plus"
```

```
g++ -std=c++17 -Iinclude main.cpp -o app.exe -lws2_32
```

```
.\app.exe
```

Depois abra:

```
http://localhost:8080
```