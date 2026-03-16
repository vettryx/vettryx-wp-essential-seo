# VETTRYX WP Essential SEO

> ⚠️ **Atenção:** Este repositório atua exclusivamente como um **Submódulo** do ecossistema principal `VETTRYX WP Core`. Ele não deve ser instalado como um plugin standalone (isolado) nos clientes.

Este submódulo é o motor proprietário de SEO da VETTRYX Tech. Desenvolvido com a premissa de "Zero Bloatware", ele substitui a necessidade de plugins pesados de mercado (como Yoast ou Rank Math), entregando apenas as funcionalidades de tráfego orgânico, roteamento e automação que realmente impactam o ranqueamento, sem sacrificar a performance do banco de dados ou a velocidade do front-end.

## 🚀 Funcionalidades

* **Fundações On-Page (Metaboxes):** Injeção cirúrgica de Meta Titles, Meta Descriptions e Open Graph (para redes sociais) no `<head>`, com sistema inteligente de fallback (geração automática de resumos e captura de imagens destacadas) caso o cliente esqueça de preencher.
* **Gerenciador de Sitemap XML:** Controle dinâmico sobre a API de Sitemaps nativa do WordPress. Permite a escolha de uma URL customizada segura, remoção de taxonomias inúteis (evitando conteúdo duplicado) e inclusão dinâmica de Custom Post Types (como `/projects/`).
* **Guardião de Tráfego (Monitor 404 & Redirecionamentos 301):** Um painel duplo nativo que escuta acessos falhos (404) e registra um log em tempo real. Permite transformar esses erros em redirecionamentos permanentes (301) instantaneamente para recuperar tráfego perdido.
* **Automações e Schema (JSON-LD):** Varredura em tempo de execução via `preg_replace_callback` que injeta o atributo `alt` em imagens vazias. Inclui também um gerador dinâmico de Schema Markup para Artigos e suporte nativo a **LocalBusiness/Organization**, injetando os dados físicos da empresa no código-fonte.
* **Indexação Instantânea (API Push):** Gatilho assíncrono conectado ao botão "Publicar/Atualizar". Dispara um Ping RPC instantâneo para motores de busca avisando sobre o novo conteúdo, acelerando o processo de rastreamento.

## ⚙️ Arquitetura e Deploy (CI/CD)

Este repositório não gera mais arquivos `.zip` para instalação manual. O fluxo de deploy é 100% automatizado:

1. Qualquer push na branch `main` deste repositório dispara um webhook (Repository Dispatch) para o repositório principal do Core.
2. O repositório do Core puxa este código atualizado para dentro da pasta `/modules/`.
3. O GitHub Actions do Core empacota tudo e gera uma única Release oficial.

## 📖 Como Usar

Uma vez que o **VETTRYX WP Core** esteja instalado e o módulo Essential SEO ativado no painel do cliente:

1. **On-Page:** Nas telas de edição de Páginas, Posts ou Projetos, desça até a caixa "🚀 VETTRYX SEO" para definir títulos e resumos personalizados.
2. **Painel de Configuração:** Acesse o menu lateral **SEO Manager** no WordPress.
    * Na aba de **Sitemap**, defina a URL principal e quais conteúdos o Google deve rastrear.
    * Na aba **Local SEO (Schema)**, preencha os dados da empresa (Endereço, Telefone, CEP) para gerar o cartão de visitas invisível para o Google.
    * No submenu **Redirect Manager**, acompanhe o log de erros 404 na primeira aba e crie/gerencie suas regras ativas de Redirecionamento 301 na segunda aba.

---

**VETTRYX Tech**
*Transformando ideias em experiências digitais.*
