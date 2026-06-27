<!DOCTYPE html>
<html lang="en-US" dir="ltr">
    <head>
        <title>AI Assistant</title>
        <meta name="title" content="AI Assistant">
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="browser-based ai assistant">
        <style>
            #chat {
                border: 1px solid #ccc;
                height: 300px;
                overflow-y: auto;
                padding: 10px;
                margin-bottom: 10px;
            }
            input {
                width: 75%;
                padding: 8px;
            }
            button {
                padding: 8px;
            }
            #status {
                margin-bottom: 10px;
                font-weight: bold;
            }
        </style>
    </head>
    <body>

        <div id="status">Loading model ...</div>
        <div id="chat"></div>
        <input id="prompt" placeholder="Write your prompt">
        <button id="send">Send</button>
        <button id="clear">Clear</button>

        <script type="module">

            import { pipeline, env, TextStreamer } from 'https://cdn.jsdelivr.net/npm/@huggingface/transformers@latest';

            //---------------------------------------

            env.allowLocalModels = true;
            env.allowRemoteModels = false;
            env.localModelPath = './model/';
            const MODEL = "gemma-3-270m-it-ONNX", MAX_HISTORY = 5;
            let device = "wasm";
            /*if (navigator.gpu) {
             device = "webgpu";
             }*/
            const chat = document.querySelector("#chat");
            const input = document.querySelector("#prompt");
            const sendBtn = document.querySelector("#send");
            const clearBtn = document.querySelector("#clear");
            const status = document.querySelector("#status");
            let generator = null, busy = false;
            const SYSTEM_PROMPT = `You are a general knowledge assistant.
                                   Answer only in English.
                                   Provide short, clear answers in one paragraph.
                                   Use your existing knowledge.
                                   If you are unsure, say you do not know.
                                   Do not invent facts.`;
            let messages = [
                {
                    role: "system",
                    content: SYSTEM_PROMPT
                }
            ];

            //---------------------------------------

            function addMessage(role, text = "") {
                const div = document.createElement("div");
                const title = document.createElement("b");
                title.textContent = role + " : ";
                const body = document.createElement("span");
                body.textContent = text;
                div.append(title, body);
                chat.appendChild(div);
                chat.scrollTop = chat.scrollHeight;
                return body;
            }

            //---------------------------------------

            function cleanHistory() {
                while (messages.length > MAX_HISTORY * 2 + 1) {
                    messages.splice(1, 2);
                }
            }

            //---------------------------------------

            (async() => {
                status.textContent = "Loading model ...";
                try {

                    const start = performance.now();
                    generator = await pipeline("text-generation", MODEL, {device: device, dtype: "fp16"});
                    const time = ((performance.now() - start) / 1000).toFixed(1);
                    status.textContent = `Ready ✔ ${time}s`;
                    addMessage("System", "Gemma is ready");
                } catch (e) {

                    status.textContent = "Model error";
                    addMessage("Error", e.message);
                }
            })();

            //---------------------------------------

            async function sendMessage() {

                if (busy || !generator)
                    return;

                const text = input.value.trim();

                if (!text)
                    return;

                busy = true;
                sendBtn.disabled = true;
                input.disabled = true;
                input.value = "";

                addMessage("You", text);

                messages.push({
                    role: "user",
                    content: text
                });

                cleanHistory();

                const assistant = addMessage("Assistant", "");
                let answer = "";

                try {

                    status.textContent = "Thinking...";

                    const streamer = new TextStreamer(generator.tokenizer, {
                        skip_prompt: true,
                        skip_special_tokens: true,
                        callback_function(token) {
                            answer += token;
                            requestAnimationFrame(() => {
                                assistant.textContent = answer;
                                chat.scrollTop = chat.scrollHeight;
                            });
                        }
                    });

                    await generator(messages, {
                        max_new_tokens: 160,
                        do_sample: false,
                        repetition_penalty: 1.1,
                        streamer
                    });

                    messages.push({
                        role: "assistant",
                        content: answer
                    });

                    cleanHistory();

                    status.textContent = "Ready ✔";
                } catch (e) {

                    assistant.textContent = "Error: " + e.message;
                } finally {

                    busy = false;
                    sendBtn.disabled = false;
                    input.disabled = false;
                }
            }

            sendBtn.onclick = sendMessage;
            input.onkeydown = e => {
                if (e.key === "Enter")
                    sendMessage();
            };

            //---------------------------------------

            clearBtn.onclick = () => {
                chat.innerHTML = "";
                messages = [{
                        role: "system",
                        content: SYSTEM_PROMPT
                    }];
            };
        </script>

    </body>
</html>
