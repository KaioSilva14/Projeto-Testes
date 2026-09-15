#include "httplib.h"
#include "json.hpp"

#include <algorithm>
#include <fstream>
#include <iostream>
#include <mutex>
#include <string>
#include <vector>

using json = nlohmann::json;
using namespace httplib;

struct Tarefa {
    int id{};
    std::string titulo;
    std::string descricao;
    std::string prioridade;
    bool concluida{};
};

void to_json(json& j, const Tarefa& t) {
    j = json{{"id", t.id}, {"titulo", t.titulo}, {"descricao", t.descricao},
             {"prioridade", t.prioridade}, {"concluida", t.concluida}};
}

void from_json(const json& j, Tarefa& t) {
    j.at("id").get_to(t.id);
    j.at("titulo").get_to(t.titulo);
    j.value("descricao", "").swap(t.descricao);
    j.value("prioridade", "media").swap(t.prioridade);
    t.concluida = j.value("concluida", false);
}

const std::string ARQUIVO_DADOS = "dados/tarefas.json";
std::vector<Tarefa> tarefas;
std::mutex mutexTarefas;

void salvarTarefas() {
    std::ofstream arquivo(ARQUIVO_DADOS);
    arquivo << json(tarefas).dump(4);
}

void carregarTarefas() {
    std::ifstream arquivo(ARQUIVO_DADOS);
    if (!arquivo) {
        salvarTarefas();
        return;
    }

    try {
        json dados;
        arquivo >> dados;
        tarefas = dados.get<std::vector<Tarefa>>();
    } catch (...) {
        std::cerr << "Aviso: nao foi possivel ler tarefas.json. Lista iniciada vazia.\n";
        tarefas.clear();
    }
}

void responderErro(Response& resposta, int status, const std::string& mensagem) {
    resposta.status = status;
    resposta.set_content(json{{"erro", mensagem}}.dump(), "application/json; charset=UTF-8");
}

int main() {
    Server servidor;
    carregarTarefas();

    servidor.set_mount_point("/", "public");

    servidor.Get("/api/tarefas", [](const Request&, Response& resposta) {
        std::lock_guard<std::mutex> bloqueio(mutexTarefas);
        resposta.set_content(json(tarefas).dump(), "application/json; charset=UTF-8");
    });

    servidor.Post("/api/tarefas", [](const Request& requisicao, Response& resposta) {
        try {
            json corpo = json::parse(requisicao.body);
            std::string titulo = corpo.value("titulo", "");
            if (titulo.empty()) {
                responderErro(resposta, 400, "O titulo e obrigatorio.");
                return;
            }

            Tarefa nova;
            nova.titulo = titulo;
            nova.descricao = corpo.value("descricao", "");
            nova.prioridade = corpo.value("prioridade", "media");
            nova.concluida = false;

            std::lock_guard<std::mutex> bloqueio(mutexTarefas);
            nova.id = tarefas.empty() ? 1 : tarefas.back().id + 1;
            tarefas.push_back(nova);
            salvarTarefas();
            resposta.status = 201;
            resposta.set_content(json(nova).dump(), "application/json; charset=UTF-8");
        } catch (...) {
            responderErro(resposta, 400, "JSON invalido.");
        }
    });

    servidor.Put(R"(/api/tarefas/(\d+))", [](const Request& requisicao, Response& resposta) {
        try {
            int id = std::stoi(requisicao.matches[1]);
            json corpo = json::parse(requisicao.body);
            std::lock_guard<std::mutex> bloqueio(mutexTarefas);

            auto it = std::find_if(tarefas.begin(), tarefas.end(),
                [id](const Tarefa& tarefa) { return tarefa.id == id; });
            if (it == tarefas.end()) {
                responderErro(resposta, 404, "Tarefa nao encontrada.");
                return;
            }

            if (corpo.contains("titulo")) it->titulo = corpo.value("titulo", it->titulo);
            if (corpo.contains("descricao")) it->descricao = corpo.value("descricao", it->descricao);
            if (corpo.contains("prioridade")) it->prioridade = corpo.value("prioridade", it->prioridade);
            if (corpo.contains("concluida")) it->concluida = corpo.value("concluida", it->concluida);

            salvarTarefas();
            resposta.set_content(json(*it).dump(), "application/json; charset=UTF-8");
        } catch (...) {
            responderErro(resposta, 400, "Dados invalidos.");
        }
    });

    servidor.Delete(R"(/api/tarefas/(\d+))", [](const Request& requisicao, Response& resposta) {
        int id = std::stoi(requisicao.matches[1]);
        std::lock_guard<std::mutex> bloqueio(mutexTarefas);
        auto tamanhoAntes = tarefas.size();
        tarefas.erase(std::remove_if(tarefas.begin(), tarefas.end(),
            [id](const Tarefa& tarefa) { return tarefa.id == id; }), tarefas.end());

        if (tarefas.size() == tamanhoAntes) {
            responderErro(resposta, 404, "Tarefa nao encontrada.");
            return;
        }
        salvarTarefas();
        resposta.set_content(json{{"mensagem", "Tarefa removida."}}.dump(), "application/json; charset=UTF-8");
    });

    std::cout << "Organiza++ rodando em http://localhost:8080\n";
    std::cout << "Pressione Ctrl+C para encerrar.\n";
    servidor.listen("0.0.0.0", 8080);
}

// Compilacao no Windows (PowerShell):
// g++ -std=c++17 -Iinclude main.cpp -o organiza.exe -lws2_32
// Execucao: .\\organiza.exe
