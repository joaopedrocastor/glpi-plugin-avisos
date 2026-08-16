# Plugin de Avisos ao Usuário — GLPI 10.0.x

Exibe avisos configuráveis pela TI em modal no portal de autoatendimento,
com segmentação por entidade/perfil, três comportamentos (informativo, com
ciência, travante) e registro de visualização por usuário.

Desenvolvido do zero. Não deriva de nenhum plugin existente.

## Estado atual

Marco 1 — **estrutura e modelo de dados** (esta entrega):

- `setup.php` / `hook.php` — bootstrap e instalação/desinstalação.
- Tabelas da seção 15: `glpi_plugin_avisos_alerts`, `_targets`, `_reads`.
- Modelo de dados em `inc/` (`PluginAvisosAlert`, `PluginAvisosTarget`,
  `PluginAvisosRead`).
- Direitos por perfil (seção 13) em `PluginAvisosProfile`, incluindo o
  **bypass de aviso travante** (P0).
- Traduções pt_BR.

Marcos seguintes: telas de administração (`front/`), endpoints AJAX
(`ajax/`) e a camada de exibição do modal/faixa no portal.

## Instalação

1. Copie a pasta `avisos/` para `<glpi>/plugins/`.
2. Compile as traduções (necessário para o GLPI carregar o pt_BR):

   ```bash
   cd plugins/avisos/locales && msgfmt pt_BR.po -o pt_BR.mo
   ```

3. Em **Configurar → Plugins**, instale e ative o **Avisos ao usuário**.
4. Ajuste os direitos por perfil em **Administração → Perfis → Avisos**.

## Requisitos

- GLPI >= 10.0.0 e < 11.0.0.
- Extensão gettext para compilar `.mo` (ambiente de build).

## Convenções de arquitetura

Regras de negócio e acesso a dados (`inc/`) ficam separados da camada de
exibição (JS/CSS/templates), para que uma futura migração ao GLPI 11
concentre o retrabalho apenas na injeção de JS/CSS e nos templates
(seção 5 da especificação).
