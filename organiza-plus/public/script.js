const API = "/api/tarefas";
let tarefas = [];

const lista = document.querySelector("#listaTarefas");
const vazio = document.querySelector("#mensagemVazia");
const modal = document.querySelector("#modal");

async function carregarTarefas() {
    try {
        const resposta = await fetch(API);
        tarefas = await resposta.json();
        renderizar();
    } catch (erro) {
        vazio.textContent = "Nao foi possivel conectar ao servidor C++.";
        vazio.style.display = "block";
    }
}

function renderizar() {
    const busca = document.querySelector("#pesquisa").value.toLowerCase();
    const filtro = document.querySelector("#filtro").value;
    const filtradas = tarefas.filter(tarefa => {
        const combinaTexto = tarefa.titulo.toLowerCase().includes(busca) || tarefa.descricao.toLowerCase().includes(busca);
        const combinaFiltro = filtro === "todas" ||
            (filtro === "pendentes" && !tarefa.concluida) ||
            (filtro === "concluidas" && tarefa.concluida) ||
            (filtro === "alta" && tarefa.prioridade === "alta");
        return combinaTexto && combinaFiltro;
    });

    lista.innerHTML = filtradas.map(tarefa => `
        <article class="tarefa ${tarefa.concluida ? "concluida" : ""}">
            <input class="check" type="checkbox" ${tarefa.concluida ? "checked" : ""} onchange="alternarTarefa(${tarefa.id}, this.checked)">
            <div class="info"><h3>${escapar(tarefa.titulo)}</h3><p>${escapar(tarefa.descricao) || "Sem descricao"}</p></div>
            <span class="etiqueta ${tarefa.prioridade}">${tarefa.prioridade}</span>
            <button class="excluir" title="Excluir tarefa" onclick="excluirTarefa(${tarefa.id})">&times;</button>
        </article>`).join("");
    vazio.style.display = filtradas.length ? "none" : "block";
    atualizarResumo();
}

function atualizarResumo() {
    const concluidas = tarefas.filter(t => t.concluida).length;
    document.querySelector("#total").textContent = tarefas.length;
    document.querySelector("#pendentes").textContent = tarefas.length - concluidas;
    document.querySelector("#concluidas").textContent = concluidas;
    document.querySelector("#progresso").textContent = tarefas.length ? Math.round(concluidas / tarefas.length * 100) + "%" : "0%";
}

async function adicionarTarefa(evento) {
    evento.preventDefault();
    const corpo = { titulo: document.querySelector("#titulo").value.trim(), descricao: document.querySelector("#descricao").value.trim(), prioridade: document.querySelector("#prioridade").value };
    await fetch(API, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(corpo) });
    evento.target.reset(); fecharModal(); carregarTarefas();
}

async function alternarTarefa(id, concluida) {
    await fetch(`${API}/${id}`, { method: "PUT", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ concluida }) });
    carregarTarefas();
}

async function excluirTarefa(id) {
    if (!confirm("Deseja realmente excluir esta tarefa?")) return;
    await fetch(`${API}/${id}`, { method: "DELETE" });
    carregarTarefas();
}

function escapar(texto) { const div = document.createElement("div"); div.textContent = texto; return div.innerHTML; }
function abrirModal() { modal.classList.remove("escondido"); document.querySelector("#titulo").focus(); }
function fecharModal() { modal.classList.add("escondido"); }

document.querySelector("#formulario").addEventListener("submit", adicionarTarefa);
document.querySelector("#abrirModal").addEventListener("click", abrirModal);
document.querySelector("#fecharModal").addEventListener("click", fecharModal);
document.querySelector("#pesquisa").addEventListener("input", renderizar);
document.querySelector("#filtro").addEventListener("change", renderizar);
modal.addEventListener("click", evento => { if (evento.target === modal) fecharModal(); });
carregarTarefas();
