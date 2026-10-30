"""Compose language-neutral article covers from product packshots (white backgrounds).

Usage: put the products' main images (WebP from the media library) in ./src as
p<post ID>.webp, adjust the file names below and run `python3 make_covers.py` in this folder.
"""
import math
from PIL import Image, ImageChops, ImageDraw, ImageFilter

W, H = 1600, 900

def background(glow_x, glow_y):
    base = Image.new('RGB', (W, H), (232, 235, 240))
    # Soft radial light in the centre.
    light = Image.new('L', (W, H), 0)
    ImageDraw.Draw(light).ellipse((W * .08, -H * .25, W * .92, H * 1.15), fill=255)
    light = light.filter(ImageFilter.GaussianBlur(220))
    base = Image.composite(Image.new('RGB', (W, H), (255, 255, 255)), base, light)
    # Laser-orange glow under the products.
    glow = Image.new('L', (W, H), 0)
    ImageDraw.Draw(glow).ellipse((glow_x - 520, glow_y - 120, glow_x + 520, glow_y + 160), fill=120)
    glow = glow.filter(ImageFilter.GaussianBlur(120))
    base = Image.composite(Image.new('RGB', (W, H), (255, 150, 105)), base, glow)
    # Thin diagonal beam as a brand accent.
    beam = Image.new('L', (W, H), 0)
    ImageDraw.Draw(beam).line((W * .62, -40, W * 1.05, H * .55), fill=170, width=3)
    beam = beam.filter(ImageFilter.GaussianBlur(1.2))
    return Image.composite(Image.new('RGB', (W, H), (255, 90, 31)), base, beam)

def main_object_box(image):
    """Bounding box of the largest non-white region, so award badges are left out."""
    scale = 8
    small = ImageChops.invert(image.convert('L')).point(lambda v: 255 if v > 12 else 0)
    small = small.resize((image.width // scale, image.height // scale), Image.BOX).point(lambda v: 255 if v > 20 else 0)
    w, h = small.size
    pixels = small.load()
    seen = set()
    best = None
    for sy in range(h):
        for sx in range(w):
            if pixels[sx, sy] == 0 or (sx, sy) in seen:
                continue
            stack, area = [(sx, sy)], 0
            seen.add((sx, sy))
            x0, y0, x1, y1 = sx, sy, sx, sy
            while stack:
                x, y = stack.pop()
                area += 1
                x0, y0, x1, y1 = min(x0, x), min(y0, y), max(x1, x), max(y1, y)
                for nx, ny in ((x + 1, y), (x - 1, y), (x, y + 1), (x, y - 1)):
                    if 0 <= nx < w and 0 <= ny < h and pixels[nx, ny] and (nx, ny) not in seen:
                        seen.add((nx, ny))
                        stack.append((nx, ny))
            if best is None or area > best[0]:
                best = (area, (x0, y0, x1, y1))
    x0, y0, x1, y1 = best[1]
    return (max(0, x0 * scale - scale), max(0, y0 * scale - scale), min(image.width, (x1 + 2) * scale), min(image.height, (y1 + 2) * scale))

def packshot(path, height):
    image = Image.open(path).convert('RGB')
    image = image.crop(main_object_box(image))
    ratio = height / image.height
    return image.resize((round(image.width * ratio), height), Image.LANCZOS)

def place(canvas, image, centre_x, bottom):
    x, y = round(centre_x - image.width / 2), round(bottom - image.height)
    region = canvas.crop((x, y, x + image.width, y + image.height))
    canvas.paste(ImageChops.multiply(region, image), (x, y))

def cover(name, items, glow=(800, 700)):
    canvas = background(*glow)
    for path, height, centre_x, bottom in items:
        place(canvas, packshot('src/' + path, height), centre_x, bottom)
    canvas.save(name, 'WEBP', quality=82, method=6)

# Laser types: diode (S1), CO2 (P3), MOPA (F2 Ultra), UV (F2 Ultra UV), side by side.
cover('cover-laser-types.webp', [('p24.webp', 190, 270, 720), ('p23.webp', 210, 640, 730), ('p117.webp', 300, 1000, 740), ('p116.webp', 330, 1340, 750)], glow=(800, 720))
# Business ideas: M2, F2, F2 Ultra UV.
cover('cover-laser-business.webp', [('p6.webp', 230, 380, 740), ('p22.webp', 400, 800, 770), ('p116.webp', 380, 1200, 760)], glow=(800, 740))
# Laser welding: MetalFab.
cover('cover-laser-welding.webp', [('p118.webp', 620, 800, 800)], glow=(800, 760))
print('done')
