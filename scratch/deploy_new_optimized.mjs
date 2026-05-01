import fs from 'fs';

const apiKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJjZDBlYmZhYy1lNWViLTQ5N2YtYjI5Zi0yMzdkZTk1MjI1MzQiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiNzcyYjIwZjMtMGQ2Mi00YWM4LWIxMmMtMTYzM2VhMzE1MWNlIiwiaWF0IjoxNzc2ODUxNjE2LCJleHAiOjE3ODQ2MDY0MDB9.BJoPXkyV-2bXNX7VsVjOTEfxuZrrRG_iF6SbNVFtLMQ';
const workflowId = 'jMCUhLQPpqFHvo1V';
const workflowPath = 'backend/WORKFLOW_KILATECH_V2_ECONOMY.json';

async function deploy() {
    console.log(`Updating workflow ${workflowId} in n8n...`);
    const workflowData = JSON.parse(fs.readFileSync(workflowPath, 'utf8'))[0];

    const cleanSettings = {};
    if (workflowData.settings.executionOrder) cleanSettings.executionOrder = workflowData.settings.executionOrder;
    if (workflowData.settings.saveExecutionProgress) cleanSettings.saveExecutionProgress = workflowData.settings.saveExecutionProgress;
    if (workflowData.settings.saveManualExecutions) cleanSettings.saveManualExecutions = workflowData.settings.saveManualExecutions;

    const res = await fetch(`http://localhost:5678/api/v1/workflows/${workflowId}`, {
        method: 'PUT',
        headers: {
            'X-N8N-API-KEY': apiKey,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            name: workflowData.name,
            nodes: workflowData.nodes,
            connections: workflowData.connections,
            settings: cleanSettings
        })
    });

    if (res.ok) {
        console.log('✅ SUCCESS: Workflow updated successfully.');
    } else {
        console.error(`❌ ERROR: Failed to update workflow: ${res.status} ${await res.text()}`);
    }
}

deploy();
