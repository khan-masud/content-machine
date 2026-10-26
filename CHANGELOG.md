# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
once it reaches 1.0.

## [Unreleased]

### Added
- Post categories (`recent`, `politics`, `analysis`, `funny`, `sarcasm`,
  `techy`, configured in `config/post_categories.php`). Telegram
  `/post sarcasm <text or link>` files the draft under that category and
  writes it in the category's tone; without one, the model picks the
  category. Category is editable on the post page, the API, and the MCP
  `update_post` tool. The AI provider form has free Gemini and Groq presets.
  Guide: [docs/guides/post-categories.md](docs/guides/post-categories.md).
- Photo cards: every generated post gets a 1080×1350 Bangla photo card
  rendered with Pillow + libraqm (no browser, ~35 MB while rendering). The AI
  writes the card headline and source; the card is sent to the Telegram chat
  and can be edited and re-rendered on the post page. Settings → Brand sets
  the name, website, logos, colour, font, image label, and per-category
  light/dark style, with a live preview. Requires `python3-pil` and
  `libraqm0` on the server. Guide: [docs/guides/photo-cards.md](docs/guides/photo-cards.md).
- Workspace API tokens: hashed bearer tokens minted/revoked from the Team
  page, with per-token abilities (scratchpad:read/write, ideas:read/write).
- JSON API under `/api/v1` for the Scratch Pad and Ideas: list/show/capture
  (text, link, photo, voice)/PATCH/delete/triage/media, plus ideas
  list/show/PATCH addressed by human_id. Captures record `source: api`;
  history rows are attributed to the token by name.
- `UpdateScratchpadEntryAction`: the first edit path for an entry's
  title/body/language, recording content_versions per changed field;
  dropped entries refuse edits.
- Guide: [docs/guides/api.md](docs/guides/api.md).
