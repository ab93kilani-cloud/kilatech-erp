const N8N_URL = 'http://localhost:5678/api/v1';
const API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJjZDBlYmZhYy1lNWViLTQ5N2YtYjI5Zi0yMzdkZTk1MjI1MzQiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiNzcyYjIwZjMtMGQ2Mi00YWM4LWIxMmMtMTYzM2VhMzE1MWNlIiwiaWF0IjoxNzc2ODUxNjE2LCJleHAiOjE3ODQ2MDY0MDB9.BJoPXkyV-2bXNX7VsVjOTEfxuZrrRG_iF6SbNVFtLMQ';

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
  
  const director = wf.nodes.find(n => n.name.includes("Le Directeur"));
  if (director) {
    // We use a MUCH MORE robust regex for multiline content extraction
    // This will work even with \n, \r or complex HTML
    director.parameters.bodyParametersUi = {
      parameter: [
        { name: "api_token", value: "KILATECH_7382" },
        { 
            name: "seo_title", 
            value: "={{ $node[\"Le Finaliseur\"].json.text.match(/<title>(.*?)<\\/title>/is)?.[1].trim() || $node[\"Architect\"].json.seo_title }}" 
        },
        { 
            name: "seo_description", 
            value: "={{ $node[\"Le Finaliseur\"].json.text.match(/<meta name=\"description\" content=\"(.*?)\"/is)?.[1].trim() || $node[\"Architect\"].json.seo_description }}" 
        },
        { 
            name: "seo_h1", 
            value: "={{ $node[\"Le Finaliseur\"].json.text.match(/<h1>(.*?)<\\/h1>/is)?.[1].trim() || $node[\"PRODUIT\"].json.name }}" 
        },
        { 
            name: "seo_content", 
            value: "={{ $node[\"Le Finaliseur\"].json.text }}" 
        },
        { name: "seo_status", value: "optimized" }
      ]
    };
    console.log("✅ Super-Robust Injection mappings applied to Director.");
  }

  await request(`/workflows/${masterId}`, 'PUT', {
    name: wf.name,
    nodes: wf.nodes,
    connections: wf.connections,
    settings: {}
  });

  console.log("n8n pipeline is now fully Industrialized.");
}
main().catch(console.error);
