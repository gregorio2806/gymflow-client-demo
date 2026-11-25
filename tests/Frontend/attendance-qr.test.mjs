import assert from 'node:assert/strict';
import { randomBytes } from 'node:crypto';
import { test } from 'node:test';
import QRCode from 'qrcode';
import ZXing from '@zxing/library';
import PNG from 'pngjs';

await test('printed attendance QR decodes to the exact opaque credential', async () => {
    const payload = `gym-attendance:${randomBytes(32).toString('hex')}`;
    const buffer = await QRCode.toBuffer(payload, {
        width: 320,
        margin: 4,
        errorCorrectionLevel: 'M',
    });
    const png = PNG.PNG.sync.read(buffer);
    const luminance = new Uint8ClampedArray(png.width * png.height);
    for (let pixel = 0; pixel < luminance.length; pixel++) {
        luminance[pixel] = png.data[pixel * 4];
    }
    const bitmap = new ZXing.BinaryBitmap(
        new ZXing.HybridBinarizer(
            new ZXing.RGBLuminanceSource(luminance, png.width, png.height),
        ),
    );
    assert.equal(new ZXing.QRCodeReader().decode(bitmap).getText(), payload);
});
