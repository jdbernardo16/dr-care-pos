const productName = process.argv[2];
if (!productName) {
  console.error(JSON.stringify({ error: "No product name provided" }));
  process.exit(1);
}

async function searchImage(query) {
  try {
    const searchUrl = `https://duckduckgo.com/?q=${encodeURIComponent(query)}&iax=images&ia=images`;

    const tokenResp = await fetch(searchUrl, {
      headers: {
        "User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36",
        "Accept": "text/html"
      }
    });

    if (!tokenResp.ok) {
      return { image: null, error: `Token fetch failed: ${tokenResp.status}` };
    }

    const html = await tokenResp.text();
    const vqdMatch = html.match(/vqd=([^"&]+)/);
    if (!vqdMatch) {
      return { image: null, error: "No vqd token" };
    }
    const vqd = vqdMatch[1];

    const apiResp = await fetch(
      `https://duckduckgo.com/i.js?q=${encodeURIComponent(query)}&vqd=${vqd}&o=json&p=1&v=1&f=,,,&l=us-en&ct=US`,
      {
        headers: {
          "User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36",
          "Referer": "https://duckduckgo.com/",
          "Accept": "application/json"
        }
      }
    );

    if (!apiResp.ok) {
      return { image: null, error: `API fetch failed: ${apiResp.status}` };
    }

    const data = await apiResp.json();
    const results = data.results || [];

    for (const result of results) {
      if (result.image && isValidImageUrl(result.image)) {
        return { image: result.image, thumbnail: result.thumbnail || null };
      }
    }

    return { image: null, error: "No valid images found" };
  } catch (err) {
    return { image: null, error: err.message };
  }
}

function isValidImageUrl(url) {
  if (!url) return false;
  try {
    const parsed = new URL(url);
    const ext = parsed.pathname.split('.').pop()?.toLowerCase();
    return ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext);
  } catch {
    return false;
  }
}

const result = await searchImage(productName + " product Philippines");
console.log(JSON.stringify(result));
