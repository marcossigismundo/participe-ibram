# Fontes do Participe Ibram

Este diretório é o destino para os arquivos de fonte oficiais do
gov.br Design System (Rawline) e fallback (Raleway). Por se tratarem
de arquivos binários pesados, eles **não são versionados** neste
repositório.

## Por que self-host?

Ambientes governamentais frequentemente operam atrás de firewalls com
whitelist restritiva. Carregar fontes via Google Fonts ou jsDelivr
quebra a aplicação em órgãos parceiros e não atende ao requisito de
soberania de assets. Self-host garante:

- Funcionamento offline (intranet do Ibram)
- Conformidade com IN SECOM 8/2014 (Padrão gov.br)
- Privacidade (nenhuma requisição de terceiros leak fingerprint)

## Como obter as fontes

### 1. Rawline (fonte primária — DSGov)

Download oficial: <https://font.download/font/rawline>

Baixe o ZIP e extraia para este diretório os pesos:

```
src/Presentation/Assets/Fonts/
├── rawline-300.woff2
├── rawline-400.woff2
├── rawline-500.woff2
├── rawline-600.woff2
└── rawline-700.woff2
```

### 2. Raleway (fallback)

Google Fonts: <https://fonts.google.com/specimen/Raleway>

Use o Google Webfonts Helper para baixar pacote self-host:
<https://gwfh.mranftl.com/fonts/raleway>

Pesos a baixar (woff2):

```
├── raleway-300.woff2
├── raleway-400.woff2
├── raleway-500.woff2
├── raleway-600.woff2
└── raleway-700.woff2
```

## Como ativar no plugin

Após colocar os arquivos aqui, adicione `@font-face` no
`assets/dist/css/participe-ibram-public.css` (logo após o `:root` de
tokens) ou em arquivo dedicado `assets/dist/css/fonts.css` carregado
antes do CSS principal:

```css
@font-face {
  font-family: 'Rawline';
  src: url('../../src/Presentation/Assets/Fonts/rawline-400.woff2') format('woff2');
  font-weight: 400;
  font-style: normal;
  font-display: swap;
}
/* repetir para cada peso */
```

## Licenças

- Rawline: SIL Open Font License 1.1 (verificar arquivo LICENSE no zip).
- Raleway: SIL Open Font License 1.1.

Mantenha cópia das licenças neste diretório quando importar as fontes.

## Quem precisa fazer este passo?

O administrador de sistema responsável pelo deploy do plugin. Esta
ação é manual e única; depois disso o build segue normalmente.
