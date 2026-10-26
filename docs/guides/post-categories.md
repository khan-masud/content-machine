# Post categories and free AI setup

Every post can carry an editorial category. The category decides the tone the
AI writes a draft in and the [photo card](photo-cards.md) style.

## Categories

| Key | Bangla | Telegram words | Tone |
|---|---|---|---|
| `recent` | সাম্প্রতিক | `recent`, `সাম্প্রতিক` | Short, neutral news update that names the source |
| `politics` | রাজনীতি | `politics`, `political`, `রাজনীতি` | Factual and balanced, every claim attributed, no invented quotes |
| `analysis` | বিশ্লেষণ | `analysis`, `বিশ্লেষণ` | A few clear points with context and a takeaway |
| `funny` | মজার | `funny`, `humor`, `humour`, `মজার`, `মজা` | Light-hearted, short punchline, never mocks people for who they are |
| `sarcasm` | ব্যঙ্গ | `sarcasm`, `sarcastic`, `satire`, `ব্যঙ্গ` | Dry wit aimed at the situation, facts kept accurate |
| `techy` | প্রযুক্তি | `techy`, `tech`, `technology`, `প্রযুক্তি` | Technology explained simply, jargon explained |

Labels, aliases, and tone text live in `config/post_categories.php`. Edit that
file to change a tone or add an alias; the Telegram help, the post page, the
API validation, and the MCP schema all read from it.

## Where you set it

- **Telegram:** `/post sarcasm <text or link>`. Only the first word is checked,
  so `/post a funny thing happened` keeps its text and lets the AI choose. A
  bare `/post funny` waits for the next photo, voice note, or text and keeps the
  category for it.
- **AI choice:** without a category the model picks one of the keys above and
  writes in that tone. An unknown answer leaves the post uncategorised; it never
  fails the draft.
- **Post page:** the Overview tab has a Category dropdown. Changing it does not
  reset the post's approval.
- **API / MCP:** `category` on `POST /api/v1/posts`, `PATCH /api/v1/posts/{human_id}`,
  and the MCP `update_post` tool. Send `null` to clear it.

## Free AI provider (Google Gemini)

Drafting needs one text model. Google Gemini has a free tier, writes Bangla
well, and exposes an OpenAI-compatible endpoint, so it works with the existing
"OpenAI-style" provider:

1. Create a key at <https://aistudio.google.com/apikey>.
2. In **Settings → AI providers → Add Provider**, click the **Google Gemini**
   preset. It fills the provider format (OpenAI-style), the label, and the base
   URL `https://generativelanguage.googleapis.com/v1beta/openai`.
3. Paste the key, save, then **Verify** to load the model list.
4. Add a Flash or Flash-Lite model from the loaded list to the default
   chain, and to the vision chain if you want photo drafts. Pick from the list
   the provider returns rather than a remembered name: Google retires and
   renames models often, and only some of them are on the free tier.

The **Groq** preset (`https://api.groq.com/openai/v1`) is a second free option
for text (for example `openai/gpt-oss-120b`; Groq retired its Llama 3.3
models in 2026). Free-tier limits and terms change; check them in each provider's
console. Google may use free-tier prompts to improve its products.

Groq also transcribes voice notes for free with Whisper (`whisper-large-v3`).
Gemini's OpenAI-compatible API has no transcription, so voice notes skip it.
See [Voice notes with Groq](../getting-started/vps.md#voice-notes-with-groq).
