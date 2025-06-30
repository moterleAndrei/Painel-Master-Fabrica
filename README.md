# Painel Master de Fábricas

Este plugin WordPress permite ao administrador master visualizar e gerenciar todas as fábricas e seus revendedores conectados, centralizando informações de desempenho, status e operações.

## Estrutura
- `painel-master.php`: Arquivo principal do plugin.
- `assets/`: Scripts e estilos do painel.
- `includes/`: Funções auxiliares e integrações.
- `templates/`: Templatep HTML/PHP para o painel.

## Funcionalidades
- Cadastro de fábricas (URLs das lojas e tokens de acesso)
- Consulta via REST API dos dados de cada loja/revendedor
- Dashboard com:
  - Número de revendedores por fábrica
  - Status dos revendedores (ativos, inativos, desligados)
  - Acompanhamento geral das operações

## Como funciona
1. O plugin master consulta periodicamente (ou sob demanda) as lojas cadastradas.
2. Cada loja precisa expor uma rota REST protegida para o master buscar os dados.
3. O painel exibe as informações centralizadas para o administrador master.

## Requisitos
- WordPress 5.0+
- PHP 7.4+

## Instalação
1. Faça upload da pasta do plugin para `wp-content/plugins/`.
2. Ative o plugin no painel do WordPress.
3. Acesse o menu "Painel Master" para cadastrar fábricas e visualizar o dashboard.

## Segurança
- As integrações REST devem ser protegidas por token ou autenticação segura.

## Observações
- Este plugin depende que o plugin dos lojistas/revendedores exponha uma API REST compatível.
- O código é modular e pode ser expandido para relatórios, gráficos e integrações extras.

---
Desenvolvido por Andrei Moterle
