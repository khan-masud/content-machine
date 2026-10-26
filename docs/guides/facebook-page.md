# Facebook Page publishing

Content Machine can publish approved posts straight to one Facebook Page per
workspace, with the photo card as the image, using Facebook's free Graph API.
No PostSyncer subscription is needed.

## What happens

1. A post is approved (`/approve P-123` or the post page).
2. **Publish now** or **Schedule** on the post page, or `/post_now P-123` /
   `/schedule P-123 2026-11-01 20:00` in Telegram. When the Page is connected
   and publishing is on, Telegram uses Facebook instead of PostSyncer.
3. Scheduled posts wait in `facebook_publications`. `facebook:publish-due` runs
   every minute (Laravel scheduler) and queues the ones that are due.
4. `PublishFacebookPostJob` (queue `scratchpad`) posts the photo card with the
   Facebook caption in the Page's language, then the first comment (usually
   the source link). Without a card it uses the post's own photo, or posts text.
5. The post becomes **Posted**, the Telegram chat gets the link, and the post
   page shows **View on Facebook**.

The post must still be approved when it is sent. Editing a scheduled post
resets its approval, so the scheduled publish stops with "Approve it and
publish again" instead of posting something nobody checked.

## When Facebook does not answer

The Graph API has no idempotency key. If the connection drops or Facebook
returns a 5xx after the request was sent, the post may or may not be on the
Page. Content Machine marks it **uncertain** and never re-sends it by itself.
Open the Page and:

- the post is there → **It is on the Page** (marks it published);
- the post is not there → **Not there, retry** (asks you to confirm).

A job that dies mid-send is marked uncertain after 10 minutes by
`facebook:publish-due`. A clear rejection (bad token, missing permission) is
**failed** and can be retried right away.

## Get a Page access token that does not expire

You need to be an admin of the Page.

1. Go to <https://developers.facebook.com/apps> and **Create app**. Under
   **Content management**, pick the **Manage everything on your Page** use
   case (or *Other*, then the *Business* type). Skip the business portfolio.
   Leave the app unpublished (development mode): no App Review is needed
   while only you use it.
2. In the app, open **Use cases → Manage everything on your Page →
   Customize** and add `pages_show_list`, `pages_read_engagement`,
   `pages_manage_posts`, `pages_manage_engagement`, and
   `pages_read_user_content`. The first comment needs
   `pages_manage_engagement`, and Meta requires `pages_read_user_content`
   alongside it.
3. Open **Graph API Explorer**
   (<https://developers.facebook.com/tools/explorer>), select your app, choose
   **User Token**, and add the same permissions. Click **Generate Access
   Token**, choose **Opt in to current Pages only**, and tick only the Page
   you will publish to.
4. Make it long-lived: open the **Access Token Debugger**
   (<https://developers.facebook.com/tools/debug/accesstoken>), paste the
   token, and click **Extend Access Token**. Put the extended token back in
   Graph API Explorer.
5. Run `GET /me/accounts` and copy your Page's `id` and `access_token`. If
   `data` comes back empty (common for Pages managed through a business
   portfolio), find the Page ID under the Page's **About → Page
   transparency** and run `GET /{page-id}?fields=name,access_token` instead.
6. Check the Page token in the Access Token Debugger: **Type: Page** and
   **Expires: Never**.

Paste the Page id and token in **Settings → Facebook**, choose the caption
language, tick **Publish to this Page**, and save. Content Machine checks the
token with Facebook before saving it and stores it encrypted.

The Graph API version is `FACEBOOK_GRAPH_VERSION` (default `v23.0`). Meta
supports each version for about two years; bump it when Meta announces the end
of life.

## Limits

- One Page per workspace. Instagram comes later.
- The Page's own rules still apply: Meta can limit reach or remove posts it
  considers misleading. Keep the approval step.
