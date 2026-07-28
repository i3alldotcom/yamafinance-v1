module.exports = async function handler(req, res) {
  try {
    const body = req.body;

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
