import fs from 'fs';

const N8N_URL = 'http://localhost:5678/api/v1';
const API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJjZDBlYmZhYy1lNWViLTQ5N2YtYjI5Zi0yMzdkZTk1MjI1MzQiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiNzcyYjIwZjMtMGQ2Mi00YWM4LWIxMmMtMTYzM2VhMzE1MWNlIiwiaWF0IjoxNzc2ODUxNjE2LCJleHAiOjE3ODQ2MDY0MDB9.BJoPXkyV-2bXNX7VsVjOTEfxuZrrRG_iF6SbNVFtLMQ';

async function request(endpoint, method, payload = null) {
  const options = {
    method,
    headers: {
      'X-N8N-API-KEY': API_KEY,
      'Content-Type': 'application/json'
    }
  };
  if (payload) options.body = JSON.stringify(payload);
  const res = await fetch(`${N8N_URL}${endpoint}`, options);
  const text = await res.text();
  if (!res.ok) throw new Error(`${method} ${endpoint} failed: ${res.status} ${text}`);
  return text ? JSON.parse(text) : null;
}

async function main() {
  try {
    const masterId = "Rbx87xuCY2i1VVBQ";
    let wf = await request(`/workflows/${masterId}`, 'GET');
    
    console.log("Configuring Director's Body Parameters with Finaliseur and Architect data...");

    const director = wf.nodes.find(n => n.name.includes("Directeur"));
    if (director) {
        // We set the body parameters strictly
        director.parameters.bodyParameters = {
          parameters: [
            {
              name: "seo_title",
              value: "={{ $node[\"Architect\"].json.h1 }}"
            },
            {
              name: "seo_description",
              value: "={{ $node[\"Architect\"].json.meta_description }}"
            },
            {
              name: "seo_keywords",
              value: "={{ $node[\"Architect\"].json.keywords }}"
            },
            {
              name: "seo_content",
              value: "={{ $node[\"Le Finaliseur\"].json.text }}"
            }
          ]
        };
        // Ensure method is POST
        director.parameters.method = "POST";
    }

    await request(`/workflows/${masterId}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    });
    
    console.log("Director is now fully equipped with all SEO parameters!");

  } catch (err) {
    console.error(err.message);
  }
}
main();
