const N8N_URL = 'http://localhost:5678/api/v1';
const API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJjZDBlYmZhYy1lNWViLTQ5N2YtYjI5Zi0yMzdkZTk1MjI1MzQiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiNzcyYjIwZjMtMGQ2Mi00YWM4LWIxMmMtMTYzM2VhMzE1MWNlIiwiaWF0IjoxNzc2ODUxNjE2LCJleHAiOjE3ODQ2MDY0MDB9.BJoPXkyV-2bXNX7VsVjOTEfxuZrrRG_iF6SbNVFtLMQ';
const TELEGRAM_TOKEN = '8797554466:AAE19aeb9b0dNUyVimG1cAqvXqKgzwsSCUo';
const TELEGRAM_CHAT_ID = '1646104095';

async function request(endpoint, method, payload = null) {
  const options = {
    method,
    headers: { 'X-N8N-API-KEY': API_KEY, 'Content-Type': 'application/json' }
  };
  if (payload) options.body = JSON.stringify(payload);
  const res = await fetch(`${N8N_URL}${endpoint}`, options);
  const text = await res.text();
  if (!res.ok) throw new Error(`${method} ${endpoint} failed: ${res.status} ${text}`);
  return text ? JSON.parse(text) : null;
}

async function main() {
  const masterId = "Rbx87xuCY2i1VVBQ";
  const wf = await request(`/workflows/${masterId}`, 'GET');
  
  const manager = wf.nodes.find(n => n.name.includes("Le Manager"));
  if (manager) {
    // Switch to Telegram API (sendMessage)
    manager.parameters.method = "POST";
    manager.parameters.url = `https://api.telegram.org/bot${TELEGRAM_TOKEN}/sendMessage`;
    manager.parameters.sendQuery = false;
    manager.parameters.specifyQuery = "json";
    
    // Remove old CallMeBot JSON
    delete manager.parameters.jsonQuery;
    delete manager.parameters.jsonParameters;
    
    // Use body parameters instead (more reliable in n8n)
    manager.parameters.sendBody = true;
    manager.parameters.bodyParametersUi = {
      parameter: [
        {
          name: "chat_id",
          value: TELEGRAM_CHAT_ID
        },
        {
          name: "text",
          value: "=🚀 *Kilatech SEO Alert*\n\n✅ Produit optimisé avec succès!\n📦 Produit: {{ $node['PRODUIT'].json.name }}\n🆔 ID: {{ $node['PRODUIT'].json.id }}\n📊 Score SEO: {{ $node['🏢 11. Le Directeur'].json.seo_score }}/100\n🏆 Statut: {{ $node['🏢 11. Le Directeur'].json.seo_status }}\n\nKilatech Intelligence - Automatisation SEO"
        },
        {
          name: "parse_mode",
          value: "Markdown"
        }
      ]
    };
    manager.parameters.specifyBody = "keypair";

    console.log("Manager switched to Telegram successfully!");
  }

  await request(`/workflows/${masterId}`, 'PUT', {
    name: wf.name,
    nodes: wf.nodes,
    connections: wf.connections,
    settings: {}
  });

  // Test: Send a welcome message
  const testRes = await fetch(`https://api.telegram.org/bot${TELEGRAM_TOKEN}/sendMessage`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      chat_id: TELEGRAM_CHAT_ID,
      text: "🎉 *Kilatech SEO Bot activé !*\n\nVotre système d'automatisation SEO est maintenant connecté à Telegram.\n\nVous recevrez une notification ici à chaque produit optimisé ! 🚀",
      parse_mode: "Markdown"
    })
  });
  const testData = await testRes.json();
  if (testData.ok) {
    console.log("✅ Test message sent to Telegram successfully!");
  } else {
    console.log("❌ Test message failed:", testData.description);
  }
}
main().catch(console.error);
