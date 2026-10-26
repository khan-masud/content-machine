# Photo cards

Every generated post gets a 1080×1350 photo card: your brand bar, a large
Bangla headline with a few highlighted words, a source line, and, when the post
has a photo, that photo fading in from the bottom.

Cards are drawn by `resources/photocards/render.py` with Pillow and libraqm.
libraqm shapes Bangla conjuncts (স্ক, ন্ধ, ত্রী) the way a browser does, so no
Chromium is needed. A render takes well under a second and about 35 MB of RAM,
only while it runs.

## Server requirement

```bash
# Ubuntu / Debian
sudo apt install python3-pil libraqm0
python3 -c "from PIL import features; print(features.check('raqm'))"   # must print True
```

The Docker image installs both. Set `PHOTO_CARD_PYTHON` if `python3` is not
on the queue worker's `PATH`.

## How a card is made

1. `/post` in Telegram drafts the post. The AI also writes the card
   `headline` (key words wrapped in `[[...]]`) and a `source` line such as
   `সোর্স: প্রথম আলো`.
2. `RenderPostPhotoCardJob` (on the `scratchpad` queue) renders the card and
   sends it to the Telegram chat as a photo, with `/approve P-123` in the
   caption. If rendering fails, the chat is told why; the draft itself is kept.
3. On the post page, the **Photo card** pane shows the card. Edit the headline
   or source and click **Render again**. A new card on an approved post needs
   approval again, because the card is what gets published.

## Backgrounds

The post's own photo (for example the one sent with `/post`) always wins.
Without one, `RenderPostPhotoCardJob` looks for a background in this order and
keeps the first image it gets:

| Source | What it is | Label |
|---|---|---|
| `ai` | AI image from the card's **background idea** (written in English by the AI with the draft, editable on the post page). Cloudflare Workers AI first, then Pollinations.ai. | Brand image label (e.g. প্রতীকী চিত্র) |
| `link` | The preview image (og:image) of the link the draft came from. Downloaded only from public addresses, max 10 MB. | সংগৃহীত ছবি |
| `pexels` | A Pexels stock photo for the card's search words. Needs a Pexels key (Settings → Pexels). | ছবি: Pexels |

**Politics** uses `link → pexels → ai`, and AI prompts never ask for real,
identifiable people: an AI picture of a politician reads as a fake. The order
lives in `config/photo_cards.php` (`background_sources`) and per category in
`config/post_categories.php` (`image_sources`).

If no source returns an image, the card is text only. A provider error never
fails the card.

**New background:** the post page's **New background** button, or `/recard
P-123` in Telegram, asks every source for a different image (a new AI seed, the
next Pexels result) and renders the card again. Telegram gets the new card as a
photo.

## Settings → Image AI

| Field | Notes |
|---|---|
| Cloudflare account ID | 32 characters, shown in the Cloudflare dashboard sidebar |
| Cloudflare API token | Create one with the **Workers AI** template (read permission). Encrypted, never sent back. |
| Use Pollinations.ai | On by default. No key; slower and rate limited. |

Cloudflare's free plan includes a daily Workers AI allowance; cards use FLUX.1
schnell (`@cf/black-forest-labs/flux-1-schnell`). Check Cloudflare's Workers AI
pricing page for the current daily limit and terms. When the allowance runs
out, cards fall back to Pollinations, then link and Pexels images. With Cloudflare off
and Pollinations unchecked, only link and Pexels images are used.

The PNG is stored as a workspace media asset and referenced from
`posts.photo_card`, not attached to the post, so the PostSyncer publish path is
unchanged. [Facebook Page publishing](facebook-page.md) posts the card as the
image.

## Settings → Brand

| Field | Used for |
|---|---|
| Brand name | Brand chip when there is no logo, and the image label |
| Website or handle | Top-left of every card |
| Brand colour | Logo chip, and the accent for posts without a category |
| Card language | Language of the AI-written headline (Bangla by default). "In English" in a Telegram message overrides it for that post. |
| Font | Hind Siliguri or Anek Bangla (both OFL, bundled in `resources/fonts/photocards`) |
| Image label | Bottom-right on cards with a photo, e.g. `প্রতীকী চিত্র \| Your Page` |
| Logo / dark logo | Top-right; dark cards use the dark logo when one is set |
| Category styles | Light or dark card and accent colour per category |

The preview on the right renders a sample card with the saved brand. Pick a
category above it to see that category's style.

Category defaults (light/dark and accent) live in `config/post_categories.php`;
canvas size, fonts, and theme colours in `config/photo_cards.php`.
