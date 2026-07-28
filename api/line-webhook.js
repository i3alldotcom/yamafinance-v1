module.exports = async function handler(req, res) {
  async function safeJson(response, label) {
    const text = await response.text();
    if (!text) {
      console.error(label, 'empty body, status', response.status);
      return null;
    }
    try {
      return JSON.parse(text);
    } catch (e) {
      console.error(label, 'non-JSON body, status', response.status, 'preview', text.slice(0, 300));
      return null;
    }
  }

  try {
    const body = req.body;
    const event = body && body.events ? body.events[0] : null;
    const message = event ? event.message : null;

    if (message && message.type === 'image') {
      const token = process.env.LINE_CHANNEL_ACCESS_TOKEN;
      const visionKey = process.env.GOOGLE_VISION_API_KEY;

      try {
        const lineUrl = 'https://api-data.line.me/v2/bot/message/' + message.id + '/content';
        const msgRes = await fetch(lineUrl, {
          headers: { Authorization: 'Bearer ' + token }
        });

        if (msgRes.ok) {
          const buf = Buffer.from(await msgRes.arrayBuffer());
          const base64 = buf.toString('base64');

          body.imageBase64 = base64;

          let jpText = '';
          const visionUrl = 'https://vision.googleapis.com/v1/images:annotate?key=' + visionKey;
          const visionBody = {
            requests: [{
              image: { content: base64 },
              features: [{ type: 'DOCUMENT_TEXT_DETECTION' }]
            }]
          };
          const visionRes = await fetch(visionUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(visionBody)
          });
          const visionData = await safeJson(visionRes, 'Vision');
          if (!visionRes.ok || !visionData) {
            console.error('Vision API error', visionRes.status);
          } else {
            const r0 = visionData.responses && visionData.responses[0];
            const ann = r0 && r0.fullTextAnnotation;
            jpText = ann ? ann.text : '';
          }

          body.ocrText = jpText;
        } else {
          console.error('LINE download not ok', msgRes.status);
        }
      } catch (e) {
        console.error('OCR/Line failed', e);
      }
    }

    const nasUrl = 'http://dmcyamanashi.myqnapcloud.com/yamafinance/public/api/line.php';
    const nasRes = await fetch(nasUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-API-KEY': 'Yama072+Finance@2026'
      },
      body: JSON.stringify(body)
    });
    if (!nasRes.ok) {
      console.error('NAS failed', nasRes.status, await nasRes.text());
      res.status(500).send('NAS failed');
      return;
    }
    res.status(200).send('OK');
  } catch (error) {
    console.error('Error', error);
    res.status(500).send('Error');
  }
};
