import { expect, test } from "bun:test";
import { readFileSync } from "node:fs";

const chrome = Bun.which("google-chrome") ?? Bun.which("chromium") ?? Bun.which("chromium-browser");

const readLayout = () => {
  const css = readFileSync("app/public/css/communication.css", "utf8");
  const html = `<!doctype html><html><head><style>${css}</style></head>
    <body style="background: #111; margin: 0">
      <div class="email-columns">
        <div class="email-column" style="height: 700px"></div>
        <section class="email-column email-detail-column">
          <div class="email-detail-header" style="height: 100px"></div>
          <div class="email-detail-body content"><iframe class="email-detail-frame" sandbox="" srcdoc="&lt;p&gt;Hello&lt;/p&gt;"></iframe></div>
        </section>
      </div>
      <article class="box"><div class="email-detail-body content"><iframe class="email-detail-frame" sandbox="" srcdoc="&lt;p&gt;Hello&lt;/p&gt;"></iframe></div></article>
      <script>
        const frame = document.querySelector('.email-detail-column iframe');
        const body = frame.parentElement;
        const fallback = document.querySelector('article iframe');
        document.body.setAttribute('data-result', JSON.stringify({
          background: getComputedStyle(frame).backgroundColor,
          frameHeight: frame.getBoundingClientRect().height,
          bodyHeight: body.clientHeight,
          fallbackHeight: fallback.getBoundingClientRect().height
        }));
      </script>
    </body></html>`;
  const result = Bun.spawnSync([
    chrome!, "--headless", "--no-sandbox", "--disable-gpu", "--disable-dev-shm-usage", "--window-size=1440,900",
    "--dump-dom", `data:text/html;charset=utf-8,${encodeURIComponent(html)}`,
  ]);
  expect(result.exitCode).toBe(0);
  const output = result.stdout.toString();
  const encoded = output.match(/data-result="([^"]+)"/)?.[1];
  expect(encoded).toBeDefined();
  return JSON.parse(encoded!.replaceAll("&quot;", '"'));
};

(chrome ? test : test.skip)("HTML email uses the surrounding background", () => {
  const layout = readLayout();
  expect(layout.background).toBe("rgba(0, 0, 0, 0)");
});

(chrome ? test : test.skip)("HTML email fills available detail space with a conversation fallback", () => {
  const layout = readLayout();
  expect(layout.frameHeight).toBeGreaterThan(500);
  expect(layout.frameHeight).toBeGreaterThan(layout.bodyHeight - 30);
  expect(layout.fallbackHeight).toBeGreaterThan(0);
});
