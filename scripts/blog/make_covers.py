"""Compose language-neutral article covers from product packshots (white backgrounds).

Usage: put the products' main images in ./src (WebP from the media library as p<post ID>.webp,
or the first image of the xTool product, e.g. f1ultra.webp), adjust the file names below and run
`python3 make_covers.py` in this folder.
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
# Metal engraving: F2 Ultra (MOPA), F1 Ultra (fibre), F2 (IR).
cover('cover-laser-metal.webp', [('p22.webp', 330, 380, 740), ('f1ultra.webp', 330, 800, 750), ('p117.webp', 330, 1220, 750)], glow=(800, 740))
# Cost: from the M2 to the P3 and MetalFab.
cover('cover-laser-cost.webp', [('p6.webp', 200, 260, 735), ('p22.webp', 320, 600, 745), ('p23.webp', 210, 960, 740), ('p118.webp', 400, 1370, 770)], glow=(800, 740))
# Safety: enclosed S1, X1 and P3.
cover('cover-laser-safety.webp', [('p24.webp', 210, 330, 735), ('p5.webp', 240, 800, 745), ('p23.webp', 230, 1270, 740)], glow=(800, 740))
# DTF and UV printing: O1, Apparel Printer Plus, WonderPress 3D.
cover('cover-dtf-printing.webp', [('o1.webp', 270, 330, 745), ('apparel.webp', 270, 820, 750), ('wonderpress.webp', 290, 1290, 745)], glow=(800, 740))
# Wood: F2, S1, M2.
cover('cover-laser-wood.webp', [('f2.webp', 330, 360, 745), ('s1.webp', 210, 800, 740), ('m2.webp', 190, 1240, 735)], glow=(800, 740))
# CO2 cutter: P3 and X1.
cover('cover-co2-laser-cutter.webp', [('p3.webp', 330, 500, 760), ('x1.webp', 340, 1130, 765)], glow=(800, 740))
# Glass: F2 Ultra UV and the Business Duo set.
cover('cover-glass-engraving.webp', [('f2ultrauv.webp', 440, 520, 780), ('duo.webp', 360, 1120, 765)], glow=(800, 750))
# Laser cleaning: MetalFab and the MaxClean head.
cover('cover-laser-cleaning.webp', [('metalfab.webp', 470, 620, 790), ('maxclean.webp', 330, 1150, 720)], glow=(800, 760))
# Rotary: RA3 and RA2 Pro next to the F2 Ultra.
cover('cover-rotary-engraving.webp', [('ra3.webp', 220, 380, 735), ('f2ultra.webp', 380, 800, 760), ('ra2pro.webp', 200, 1230, 735)], glow=(800, 740))
# Stickers: M2 and its CMYK module.
cover('cover-sticker-cutter.webp', [('m2.webp', 300, 640, 760), ('m2cmyk.webp', 300, 1170, 760)], glow=(800, 750))
# School: S1, F1 Ultra and MetalFab for Education.
cover('cover-laser-school.webp', [('s1edu.webp', 170, 270, 735), ('f1ultraedu.webp', 290, 760, 750), ('metalfabedu.webp', 250, 1290, 750)], glow=(800, 740))
# F2 family: F2, F2 Ultra, F2 Ultra UV.
cover('cover-xtool-f2-comparison.webp', [('f2.webp', 330, 380, 745), ('f2ultra.webp', 380, 800, 760), ('f2ultrauv.webp', 380, 1220, 760)], glow=(800, 745))
# Sheet metal cutting: MetalFab and the compressor with air dryer.
cover('cover-metal-cutting.webp', [('metalfab.webp', 470, 580, 790), ('compressor.webp', 300, 1180, 750)], glow=(800, 760))
# Laser marking: F2 Ultra, the Auto Streamline conveyor and F1 Ultra.
cover('cover-laser-marking.webp', [('f2ultra.webp', 380, 420, 760), ('conveyor.webp', 110, 830, 760), ('f1ultra.webp', 330, 1230, 750)], glow=(800, 745))
# Photo engraving: M2, F2 Ultra UV and F2.
cover('cover-photo-engraving.webp', [('m2.webp', 220, 360, 740), ('f2ultrauv.webp', 400, 800, 765), ('f2.webp', 330, 1240, 750)], glow=(800, 745))
# Leather: P3 and M2.
cover('cover-leather-engraving.webp', [('p3.webp', 320, 560, 760), ('m2.webp', 230, 1160, 745)], glow=(800, 745))
# Paper and cardboard: M2 and S1.
cover('cover-paper-cutting.webp', [('m2.webp', 280, 540, 755), ('s1.webp', 220, 1150, 745)], glow=(800, 750))
# Events: F2, DJI Power 1000 Mini and Air Purifier Go.
cover('cover-event-engraving.webp', [('f2.webp', 340, 400, 755), ('dji.webp', 250, 840, 745), ('purifiergo.webp', 320, 1250, 755)], glow=(800, 745))
# Stone: S1, its riser base and F2 Ultra UV.
cover('cover-stone-engraving.webp', [('s1.webp', 210, 380, 740), ('riser.webp', 130, 820, 735), ('f2ultrauv.webp', 390, 1230, 765)], glow=(800, 745))
# xTool X1.
cover('cover-xtool-x1.webp', [('x1.webp', 480, 800, 790)], glow=(800, 760))
# Accessories: RA3 rotary, AP2 purifier and the Fire Safety Set.
cover('cover-laser-accessories.webp', [('ra3.webp', 200, 380, 735), ('ap2.webp', 380, 800, 765), ('firesafety.webp', 150, 1230, 730)], glow=(800, 745))
# Christmas: M2, F2 and F2 Ultra UV.
cover('cover-christmas-laser.webp', [('m2.webp', 220, 370, 740), ('f2.webp', 330, 800, 750), ('f2ultrauv.webp', 380, 1220, 760)], glow=(800, 745))
# Pricing services: F2, F2 Ultra and P3.
cover('cover-engraving-pricing.webp', [('f2.webp', 320, 340, 750), ('f2ultra.webp', 370, 760, 760), ('p3.webp', 260, 1240, 745)], glow=(800, 745))
# Maintenance: P3, its honeycomb panel and AP2 filters.
cover('cover-laser-maintenance.webp', [('p3.webp', 290, 470, 755), ('honeycombp3.webp', 150, 990, 735), ('ap2filters.webp', 140, 1360, 730)], glow=(800, 745))
# Screen printing: the Screen Printer and P3.
cover('cover-screen-printing.webp', [('screenprinter.webp', 330, 520, 760), ('p3.webp', 270, 1150, 750)], glow=(800, 750))
# Fabric: P3 and M2.
cover('cover-fabric-cutting.webp', [('p3.webp', 320, 560, 760), ('m2.webp', 230, 1160, 745)], glow=(800, 745))
# Heat press: WonderPress 3D and its craft oven module.
cover('cover-heat-press.webp', [('wonderpress.webp', 420, 620, 775), ('oven.webp', 200, 1150, 740)], glow=(800, 755))
# Jewellery: F2 Ultra, F1 Ultra and RA2 Pro.
cover('cover-jewelry-engraving.webp', [('f2ultra.webp', 380, 400, 760), ('f1ultra.webp', 330, 820, 750), ('ra2pro.webp', 200, 1240, 735)], glow=(800, 745))
# Fume extraction: AP2, IF2 inline fan and Air Purifier Go.
cover('cover-fume-extraction.webp', [('ap2.webp', 400, 420, 770), ('if2.webp', 200, 820, 740), ('purifiergo.webp', 320, 1220, 755)], glow=(800, 750))
# Welding stainless and aluminium: MetalFab and its wire feeder.
cover('cover-laser-welding-materials.webp', [('metalfab.webp', 470, 590, 790), ('wirefeeder.webp', 280, 1170, 750)], glow=(800, 760))
# Buying xTool: the demo machines F2, P3 and M2 with the MetalFab.
cover('cover-buy-xtool.webp', [('f2.webp', 280, 210, 745), ('p3.webp', 220, 580, 740), ('m2.webp', 170, 1000, 735), ('metalfab.webp', 360, 1380, 770)], glow=(800, 745))
# Home laser engraver: M2, S1 and F2.
cover('cover-home-laser-engraver.webp', [('m2.webp', 200, 330, 740), ('s1.webp', 190, 790, 740), ('f2.webp', 320, 1240, 750)], glow=(800, 745))
print('done')
