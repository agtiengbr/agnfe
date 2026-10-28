# agnfe

Módulo **Documentos da NF-e** para PrestaShop. Mantém um XML de NF-e e um DANFE em PDF por pedido, disponibiliza o DANFE ao cliente e compartilha número e chave da nota com as integrações de transporte compatíveis.

Este é o repositório canônico do módulo: [agtiengbr/agnfe](https://github.com/agtiengbr/agnfe).

## Aviso Legal

Este software é disponibilizado sem qualquer tipo de garantia, expressa ou implícita, incluindo, mas não se limitando a, garantias de comercialização, adequação a uma finalidade específica ou não violação de direitos. A utilização deste módulo é de inteira responsabilidade do usuário.

## Suporte

Serviços de suporte técnico estão disponíveis mediante contratação. Solicitações devem ser encaminhadas exclusivamente para:

**suporte@agti.eng.br**

## Instalação

1. Baixe `agnfe-<versão>.zip` na [release mais recente](https://github.com/agtiengbr/agnfe/releases/latest).
2. No painel do PrestaShop, acesse **Módulos → Gerenciador de módulos → Enviar um módulo** e selecione o ZIP. O envio já instala o módulo.
3. Em **Configurar**, selecione o estado existente **Pedido com NF-e gerada**, ou o estado equivalente usado pela loja.
4. Abra um pedido no administrativo e utilize a aba **NF-e / DANFE** para anexar o XML e o PDF.

A extensão PHP DOM deve estar habilitada. O usuário do PHP precisa conseguir criar e gravar a pasta privada `agnfe_private`, ao lado da raiz da instalação PrestaShop e fora do diretório público.

O módulo recebe documentos já emitidos. A emissão e a autorização fiscal da NF-e são realizadas pelo sistema emissor da loja.

## Documentos do pedido

- Extrai número e chave da NF-e do XML anexado e confere sua estrutura básica.
- Aceita XML e DANFE em PDF com até 10 MB por arquivo.
- Armazena os documentos fora do diretório público, com nomes aleatórios.
- Ao completar o par XML/PDF, disponibiliza o DANFE ao cliente, aplica o estado configurado e tenta enviar o aviso por e-mail.
- Permite baixar o DANFE mediante autenticação do cliente proprietário do pedido.
- A substituição de uma nota publicada exige um novo par XML/PDF; o aviso inicial não é reenviado. Se o primeiro aviso falhar, há uma ação de nova tentativa.

## Integrações de transporte

### agcorreios

O [agcorreios 5.5.0 ou superior](https://github.com/agtiengbr/agcorreios/releases/latest) consulta automaticamente o XML vinculado ao pedido quando o agnfe está ativo. Com número e chave válidos, utiliza esses dados na pré-postagem; sem nota disponível, mantém a declaração de conteúdo. A postagem não exige o DANFE em PDF nem a publicação do documento ao cliente.

### agmelhorenvio

Em uma versão do agmelhorenvio que ofereça a opção **Usar agnfe**, selecione essa opção para **número** e **chave da NF-e**. A integração compartilha o mesmo cadastro de documentos do pedido.

Na instalação, o agnfe importa XMLs anteriormente cadastrados no agmelhorenvio, se a tabela e os arquivos existirem. Os originais são preservados, e a cópia é conferida por SHA-256. Em **Configurar**, confira os pedidos com importação pendente e utilize **Conferir e importar XMLs pendentes** quando necessário. A integração do agmelhorenvio recusa a troca da fonte enquanto houver XMLs antigos pendentes.

## Validação e releases

Execute os testes de validação de documentos com PHP DOM habilitado:

```sh
php tests/validation.php
```

As releases são publicadas pelo workflow **Release** ao enviar uma tag no formato `v1.0.0`. A versão precisa coincidir em `agnfe.php` e `config.xml`. O workflow verifica sintaxe PHP, executa os testes e gera `agnfe-<versão>.zip` com a pasta raiz `agnfe/`, sem testes nem arquivos do workflow. Publicar uma release não faz deploy em lojas.

## Contribuições

Contribuições são bem-vindas. Forks e pull requests podem ser submetidos desde que estejam em conformidade com os termos da licença **GNU General Public License v3.0 (GPLv3)**.

## Licença

Este projeto está licenciado sob a [GNU General Public License v3.0](LICENSE).
