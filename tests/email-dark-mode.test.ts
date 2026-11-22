import { expect, test } from "bun:test";
import { readFileSync } from "node:fs";

const chrome = Bun.which("google-chrome") ?? Bun.which("chromium") ?? Bun.which("chromium-browser");

const renderEmail = () => {
  const php = `
    function getLinkIcons(): array { return []; }
    function formatPlainEmailBodyWithQuotes(string $body): string { return htmlspecialchars($body); }
    $selectedMailbox = ['id' => 1];
    $message = ['id' => 1, 'body_html' => '<p>Unstyled</p><div style="background: white; color: black"><p style="color: #111">Hello</p><a href="#" style="color: #123456">Link</a></div>'];
    $emailDetailShowActions = $emailDetailShowLinks = $emailDetailShowAttachments = $emailDetailIncludeLinkEditor = false;
    require 'app/views/communication/email_detail.php';`;
  const result = Bun.spawnSync(["php", "-r", php]);
  expect(result.exitCode).toBe(0);
  const frame = result.stdout.toString().match(/<iframe\b[^>]*><\/iframe>/)?.[0];
  expect(frame).toBeDefined();
  // Keep the real srcdoc and CSP, but allow same-origin inspection of computed styles.
  return frame!.replace('sandbox=""', '');
};

const checkThemes = (systemDark: boolean) => {
  const frame = renderEmail();
  const css = readFileSync("app/public/css/communication.css", "utf8");
  const html = `<!doctype html><html><head><style>${css}
    section, article { color: hsl(221, 14%, 29%); background: hsl(221, 14%, 100%); }
    html[data-theme="dark"] section, html[data-theme="dark"] article { color: hsl(221, 14%, 71%); background: hsl(221, 14%, 9%); }
    @media (prefers-color-scheme: dark) { html:not([data-theme]) section, html:not([data-theme]) article { color: hsl(221, 14%, 71%); background: hsl(221, 14%, 9%); } }
    </style></head><body>
    <section class="email-detail-column">${frame}</section><article>${frame}</article>
    <script>
      const frames = [...document.querySelectorAll('iframe')];
      let loaded = 0;
      frames.forEach(frame => frame.onload = () => {
        if (++loaded !== frames.length) return;
        const measure = () => frames.map(frame => {
          const doc = frame.contentDocument;
          return {
            frame: getComputedStyle(frame).backgroundColor,
            surroundingText: getComputedStyle(frame.parentElement).color,
            body: getComputedStyle(doc.body).backgroundColor,
            block: getComputedStyle(doc.querySelector('div')).backgroundColor,
            defaultText: getComputedStyle(doc.querySelector('body > p')).color,
            text: getComputedStyle(doc.querySelector('div p')).color,
            link: getComputedStyle(doc.querySelector('a')).color
          };
        });
        document.documentElement.setAttribute('data-theme', 'dark');
        const dark = measure();
        document.documentElement.setAttribute('data-theme', 'light');
        const light = measure();
        document.documentElement.removeAttribute('data-theme');
        document.body.setAttribute('data-result', JSON.stringify({ dark, light, system: measure() }));
      });
    </script></body></html>`;
  const args = [chrome!, "--headless", "--no-sandbox", "--disable-gpu", "--disable-dev-shm-usage", "--virtual-time-budget=2000", "--dump-dom"];
  if (systemDark) args.push("--force-dark-mode");
  const result = Bun.spawnSync([...args, `data:text/html;charset=utf-8,${encodeURIComponent(html)}`]);
  expect(result.exitCode).toBe(0);
  const encoded = result.stdout.toString().match(/data-result="([^"]+)"/)?.[1];
  expect(encoded).toBeDefined();
  return JSON.parse(encoded!.replaceAll("&quot;", '"'));
};

(chrome ? test : test.skip)("unstyled email backgrounds are transparent and explicit styles are preserved in both themes", () => {
  for (const systemDark of [false, true]) {
    const { dark, light, system } = checkThemes(systemDark);
    for (const view of [...dark, ...light]) {
      expect(view.frame).toBe("rgba(0, 0, 0, 0)");
      expect(view.body).toBe("rgba(0, 0, 0, 0)");
      expect(view.block).toBe("rgb(255, 255, 255)");
      expect(view.text).toBe("rgb(17, 17, 17)");
      expect(view.link).toBe("rgb(18, 52, 86)");
      expect(view.defaultText).toBe(view.surroundingText);
    }
    expect(dark[0].defaultText).not.toBe(light[0].defaultText);
    expect(system).toEqual(systemDark ? dark : light);
  }
});
