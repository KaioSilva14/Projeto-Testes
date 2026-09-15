#include <iostream>
#include <limits>
#include <random>

int main() {
    std::random_device rd;
    std::mt19937 gerador(rd());
    std::uniform_int_distribution<int> distribuicao(1, 100);

    char jogarNovamente = 's';

    std::cout << "=== Jogo de Adivinhacao ===\n";
    std::cout << "Tente adivinhar o numero entre 1 e 100!\n\n";

    while (jogarNovamente == 's' || jogarNovamente == 'S') {
        int numeroSorteado = distribuicao(gerador);
        int tentativa;
        int quantidadeTentativas = 0;

        do {
            std::cout << "Digite seu palpite: ";
            std::cin >> tentativa;

            if (std::cin.fail()) {
                std::cin.clear();
                std::cin.ignore(std::numeric_limits<std::streamsize>::max(), '\n');
                std::cout << "Entrada invalida. Digite um numero inteiro.\n\n";
                continue;
            }

            quantidadeTentativas++;

            if (tentativa < numeroSorteado) {
                std::cout << "Mais alto! Tente novamente.\n";
            } else if (tentativa > numeroSorteado) {
                std::cout << "Mais baixo! Tente novamente.\n";
            } else {
                std::cout << "Parabens! Voce acertou em "
                          << quantidadeTentativas << " tentativa(s)!\n";
            }
        } while (tentativa != numeroSorteado);

        std::cout << "\nDeseja jogar novamente? (s/n): ";
        std::cin >> jogarNovamente;
        std::cout << "\n";
    }

    std::cout << "Obrigado por jogar!\n";
    return 0;
}
