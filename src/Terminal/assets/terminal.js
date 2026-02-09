const input = document.getElementById("terminal-input");
const body = document.getElementById("terminal-body");

window.terminal = {
    start: function() {
        this.input = document.getElementById("terminal-input");
        this.body = document.getElementById("terminal-body");
        this.history = [];
        this.historyIndex = 0;

        this.body.addEventListener("click", () => this.input.focus());

        this.input.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                const cmd = this.input.value.trim();
                if (cmd.length > 0) {
                    this.print("user@irbis:~$ " + cmd);
                    if (cmd === "clear") {
                        window.location.reload();
                    } else if (cmd === "help") {
                        window.open("https://github.com/dulivu/irbis#configuracion", "_new");

                        this.history.push(cmd);
                        this.historyIndex = this.history.length;
                        this.input.value = "";
                    } else if (cmd.startsWith("nano ")) {
                        const width = 640;
                        const height = 480;
                        const left = (screen.width - width) / 2;
                        const top = (screen.height - height) / 2;
                        const params = `width=${width}, height=${height}, left=${left}, top=${top}`;
                        let file = cmd.slice(5).trim();
                        
                        window.open("/terminal/nano?file=" + btoa(file), "_blank", params);
                        
                        this.history.push(cmd);
                        this.historyIndex = this.history.length;
                        this.input.value = "";
                    } else if (cmd === "sql") {
                        const width = 640;
                        const height = 480;
                        const left = (screen.width - width) / 2;
                        const top = (screen.height - height) / 2;
                        const params = `width=${width}, height=${height}, left=${left}, top=${top}`;
                        
                        window.open("/terminal/sql", "_blank", params);
                        
                        this.history.push(cmd);
                        this.historyIndex = this.history.length;
                        this.input.value = "";
                    } else if (cmd.startsWith("show")) {
                        const width = 800;
                        const height = 600;
                        const left = (screen.width - width) / 2;
                        const top = (screen.height - height) / 2;
                        const params = `width=${width}, height=${height}, left=${left}, top=${top}`;
                        const path = cmd.trim().split(" ");
                        
                        window.open(path[1] || "/", "_blank", params);
                        
                        this.history.push(cmd);
                        this.historyIndex = this.history.length;
                        this.input.value = "";
                    } else {
                        this.commandRemote(cmd);
                        this.input.disabled = true;
                        this.input.value = "... procesando";
                    }
                }
            } else if (e.key === "ArrowUp") {
                e.preventDefault();
                if (this.history.length > 0 && this.historyIndex > 0) {
                    this.historyIndex -= 1;
                    this.input.value = this.history[this.historyIndex];
                }
            } else if (e.key === "ArrowDown") {
                e.preventDefault();
                if (this.history.length > 0 && this.historyIndex < this.history.length) {
                    this.historyIndex += 1;
                    if (this.historyIndex === this.history.length) {
                        this.input.value = "";
                    } else {
                        this.input.value = this.history[this.historyIndex];
                    }
                } else this.input.value = "";
            }
        });
    },

    commandRemote: async function(cmd) {
        try {
            const formData = new FormData();
            formData.append("command", cmd);

            const response = await fetch("/terminal/command", {
                method: "POST",
                body: formData
            });
            
            this.history.push(cmd);
            this.historyIndex = this.history.length;
            this.print(this.parse(await response.text()));
        } catch (err) {
            console.warn(err);
            this.print(this.parse("span.error > Error al enviar comando"));
        }
        this.input.value = "";
        this.input.disabled = false;
        this.input.focus();
    },

    print: function(text) {
        const div = document.createElement("div");
        if (text instanceof Node) {
            div.appendChild(text);
        } else {
            div.innerHTML = `<span class="muted">${text}</span>`;
        }
        this.body.insertBefore(div, this.body.lastElementChild);
        this.body.scrollTop = this.body.scrollHeight;
    },

    parse: function(definition) {
        const [left, right] = definition.split('>').map(s => s.trim());

        // ---- Parse elemento y clases ----
        const parts = left.split('.');
        const tag = parts.shift();

        if (!tag) {
            throw new Error('Elemento inválido');
        }

        const classes = parts;

        // ---- Crear elemento ----
        const el = document.createElement(tag);
        if (classes.length) {
            el.className = classes.join(' ');
        }

        // ---- Procesar texto ----
        const lines = right.split(/\r\n|\r|\n/);
        console.log(lines);

        lines.forEach((line, index) => {
            el.appendChild(document.createTextNode(line));
            if (index < lines.length - 1) {
                el.appendChild(document.createElement('br'));
            }
        });

        return el;
    }
}

terminal.start();