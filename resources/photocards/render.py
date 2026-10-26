"""Render a social photo card (PNG) from a JSON spec.

Usage: python3 render.py <spec.json> <out.png>

Uses Pillow with libraqm so Bangla conjuncts shape correctly without a
browser. The spec is written by App\\Support\\PhotoCards\\PillowPhotoCardRenderer;
see App\\Data\\PhotoCards\\PhotoCardData::toRenderSpec() for its fields.
"""

import json
import re
import sys

from PIL import Image, ImageDraw, ImageFilter, ImageFont, features

GUTTER = 64
TOP_BAR_Y = 56
HEADLINE_TOP = 230
MAX_HEADLINE_LINES = 4
LOGO_MAX_HEIGHT = 72
LOGO_MAX_WIDTH = 300


def hex_rgb(value):
    value = value.lstrip("#")
    return tuple(int(value[i:i + 2], 16) for i in (0, 2, 4))


def load_font(spec, size):
    font = ImageFont.truetype(spec["path"], size, layout_engine=ImageFont.Layout.RAQM)
    weight = spec.get("weight")
    if weight is not None:
        try:
            axes = font.get_variation_axes()
        except OSError:
            axes = []
        if axes:
            values = []
            for axis in axes:
                name = axis["name"].decode() if isinstance(axis["name"], bytes) else axis["name"]
                values.append(weight if name.lower() == "weight" else axis["default"])
            font.set_variation_by_axes(values)
    return font


def headline_tokens(text):
    """Split "[[word]]"-marked text into (word, highlighted) pairs."""
    tokens = []
    for part in re.split(r"(\[\[.*?\]\])", text):
        highlighted = part.startswith("[[") and part.endswith("]]")
        for word in part.strip("[]").split():
            tokens.append((word, highlighted))
    return tokens


def layout_headline(draw, tokens, font_spec, max_width, max_size, min_size):
    size = max_size
    while True:
        font = load_font(font_spec, size)
        space = draw.textlength(" ", font=font)
        lines = [[]]
        width = 0.0
        for word, highlighted in tokens:
            word_width = draw.textlength(word, font=font)
            if lines[-1] and width + space + word_width > max_width:
                lines.append([])
                width = 0.0
            width += (space if lines[-1] else 0) + word_width
            lines[-1].append((word, highlighted, word_width))
        if len(lines) <= MAX_HEADLINE_LINES or size <= min_size:
            return font, space, lines, size
        size -= 4


def cover(image, width, height):
    """Scale and centre-crop an image to fill width x height."""
    scale = max(width / image.width, height / image.height)
    resized = image.resize((max(1, round(image.width * scale)), max(1, round(image.height * scale))), Image.LANCZOS)
    left = (resized.width - width) // 2
    top = (resized.height - height) // 2
    return resized.crop((left, top, left + width, top + height))


def paste_logo(canvas, path, right, top):
    logo = Image.open(path).convert("RGBA")
    scale = min(LOGO_MAX_HEIGHT / logo.height, LOGO_MAX_WIDTH / logo.width, 1.0)
    logo = logo.resize((max(1, round(logo.width * scale)), max(1, round(logo.height * scale))), Image.LANCZOS)
    canvas.paste(logo, (right - logo.width, top), logo)


def render(spec):
    width, height = spec["width"], spec["height"]
    colors = {key: hex_rgb(value) for key, value in spec["colors"].items()}
    fonts = spec["fonts"]

    canvas = Image.new("RGB", (width, height), colors["background"])

    background = spec.get("background_path")
    image_top = height
    if background:
        image_height = spec.get("background_height") or round(height * 0.6)
        image_top = height - image_height
        source = Image.open(background)
        # Decode large JPEGs at reduced scale: keeps a 6000px photo from
        # costing hundreds of MB on a small server.
        source.draft("RGB", (width * 2, image_height * 2))
        scene = cover(source.convert("RGB"), width, image_height)
        fade = round(image_height * 0.4)
        mask = Image.new("L", (width, image_height), 255)
        mask_draw = ImageDraw.Draw(mask)
        for y in range(fade):
            mask_draw.line([(0, y), (width, y)], fill=int(255 * (y / fade) ** 1.6))
        canvas.paste(scene, (0, image_top), mask)

    draw = ImageDraw.Draw(canvas)
    small = load_font(fonts["regular"], 30)
    tiny = load_font(fonts["regular"], 24)

    # Brand bar: website on the left, logo (or the brand name) on the right.
    if spec.get("website"):
        draw.text((GUTTER, TOP_BAR_Y + 8), spec["website"], font=small, fill=colors["text"])
    if spec.get("logo_path"):
        paste_logo(canvas, spec["logo_path"], width - GUTTER, TOP_BAR_Y)
    elif spec.get("brand_name"):
        name_font = load_font(fonts["bold"], 30)
        name_width = draw.textlength(spec["brand_name"], font=name_font)
        box = [width - GUTTER - name_width - 36, TOP_BAR_Y, width - GUTTER, TOP_BAR_Y + 56]
        draw.rounded_rectangle(box, radius=10, fill=colors["primary"])
        draw.text(((box[0] + box[2]) / 2, TOP_BAR_Y + 28), spec["brand_name"], font=name_font,
                  fill=(255, 255, 255), anchor="mm")

    # Headline, centred; [[marked]] words use the accent colour.
    tokens = headline_tokens(spec["headline"])
    font, space, lines, size = layout_headline(draw, tokens, fonts["bold"], width - 2 * GUTTER - 32, 96, 52)
    line_height = round(size * 1.38)
    block_height = line_height * len(lines)
    top = HEADLINE_TOP if background else max(HEADLINE_TOP, (height - block_height) // 2 - 40)
    y = top
    for line in lines:
        total = sum(word_width for _, _, word_width in line) + space * (len(line) - 1)
        x = (width - total) / 2
        for word, highlighted, word_width in line:
            draw.text((x, y), word, font=font, fill=colors["accent"] if highlighted else colors["text"])
            x += word_width + space
        y += line_height

    if spec.get("source"):
        draw.text((width / 2, y + 44), spec["source"], font=tiny, fill=colors["muted"], anchor="mm")

    if not background:
        # A short accent rule keeps a text-only card from looking empty.
        rule_y = height - 150
        draw.rounded_rectangle([width / 2 - 60, rule_y, width / 2 + 60, rule_y + 8], radius=4, fill=colors["accent"])

    # Image credit label, readable on any photo thanks to a soft shadow.
    if background and spec.get("image_label"):
        label = spec["image_label"]
        position = (width - 40, height - 36)
        shadow = Image.new("RGBA", canvas.size, (0, 0, 0, 0))
        ImageDraw.Draw(shadow).text(position, label, font=tiny, fill=(0, 0, 0, 200), anchor="rs")
        canvas.paste(shadow.filter(ImageFilter.GaussianBlur(3)), (0, 0), shadow.filter(ImageFilter.GaussianBlur(3)))
        draw.text(position, label, font=tiny, fill=(255, 255, 255), anchor="rs")

    return canvas


def main():
    if len(sys.argv) != 3:
        print("usage: render.py <spec.json> <out.png>", file=sys.stderr)
        return 2
    if not features.check("raqm"):
        print("Pillow was built without libraqm; Bangla text cannot be shaped.", file=sys.stderr)
        return 3
    with open(sys.argv[1], encoding="utf-8") as handle:
        spec = json.load(handle)
    render(spec).save(sys.argv[2], "PNG", optimize=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
