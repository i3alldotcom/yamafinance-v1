export default async function handler(req, res) {
    try {
      const body = req.body;
      const event = body && body.events ? body.events[0] : null;
      const message = event ? event.message : null;

      if (message && message.type === 'image') {        const token = process.env.LINE_CHANNEL_ACCESS_TOKEN;
        const apiKey = process.env.GOOGLE_VISION_API_KEY;
        try {
          const msgRes = await fetch(
            `https://api-data.line.me/v2/bot/message/${message.id}/content`,
            { headers: { Authorization: `Bearer ${token}` } }
          );
          if (msgRes.ok) {
            const buf = Buffer.from(await msgRes.arrayBuffer());
            const base64 = buf.toString('base64');

            const visionRes = await fetch(
              `https://vision.googleapis.com/v1/images:annotate?key=${apiKey}`,
              {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  requests: [{
                    image: { content: base64 },
                    features: [{ type: 'DOCUMENT_TEXT_DETECTION' }]
                  }]
                })
              }
            );
            const visionData = await visionRes.json();
            if (!visionRes.ok) {
              console.error('Vision API error', visionRes.status, JSON.stringify(visionData));
            } else {
              const ann = visionData.responses && visionData.responses[0] &&
  visionData.responses[0].fullTextAnnotation;
              body.ocrText = ann ? ann.text : '';
              body.imageBase64 = base64;
              if (!ann) console.error('Vision: no text found in image');
            }
          } else {
            console.error('LINE download not ok', msgRes.status);
          }
        } catch (e) {
          console.error('OCR/Line failed:', e);
        }
      }

      const nasRes = await fetch(
        "http://dmcyamanashi.myqnapcloud.com/yamafinance/public/api/line.php",
        {
          method: "POST",
          headers: { "Content-Type": "application/json", "X-API-KEY": "Yama072+Finance@2026" },
          body: JSON.stringify(body)
        }
      );
      if (!nasRes.ok) { res.status(500).send("NAS failed"); return; }
      res.status(200).send("OK");
    } catch (error) {
      console.error("Error:", error);
      res.status(500).send("Error");
    }
  }
