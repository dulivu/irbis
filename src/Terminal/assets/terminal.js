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
                    this.print("user@irbis:~$ " + cmd, "muted");
                    if (cmd === "clear") {
                        window.location.reload();
                    } else if (cmd === "help") {
                        const width = 600;
                        const height = 400;
                        const left = (screen.width / 2) - (width / 2);
                        const top = (screen.height / 2) - (height / 2);
                        const params = `width=${width}, height=${height}, left=${left}, top=${top}`;
                        window.open("/cli?view=help", "_blank", params);
                    } else {
                        this.commandRemote(cmd);
                    }
                }
                this.input.value = "";
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

            const response = await fetch("/cli/command", {
                method: "POST",
                body: formData
            });
            
            this.history.push(cmd);
            this.historyIndex = this.history.length;
            this.print(await response.text());
        } catch (err) {
            console.warn(err);
            this.print("Error al enviar comando", "error");
        }
    },

    print: function(text, cssClass = "") {
        const div = document.createElement("div");
        div.innerHTML = text;
        if (cssClass) div.classList.add(cssClass);
        this.body.insertBefore(div, this.body.lastElementChild);
        this.body.scrollTop = this.body.scrollHeight;
    }
}

terminal.start();