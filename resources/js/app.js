// Makes globalThis.BarcodeDetector available everywhere: the browser's own
// implementation where it exists (Chrome on Android), a ZXing wasm fallback
// where it does not (Safari, iOS). We then code against the web standard
// rather than against a library's API.
import 'barcode-detector/polyfill';
