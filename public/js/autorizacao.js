/**
 * Janela que pede login e senha de um gerente (RF11: desconto > 10%; RF12: cancelamento).
 * Usa o <dialog> nativo do HTML. Devolve { login, senha } ou null se cancelar.
 * A conferência da senha é feita no SERVIDOR (bcrypt) — o front só coleta os dados.
 */
export function pedirAutorizacao(motivo) {
  return new Promise((resolver) => {
    const janela = document.createElement('dialog');
    const form = document.createElement('form');
    form.method = 'dialog';

    const titulo = document.createElement('h3');
    titulo.textContent = 'Autorização do gerente';
    const texto = document.createElement('p');
    texto.className = 'suave';
    texto.textContent = motivo;

    const campo = (rotulo, nome, tipo) => {
      const label = document.createElement('label');
      label.textContent = rotulo;
      const input = document.createElement('input');
      input.name = nome;
      input.type = tipo;
      input.required = true;
      input.autocomplete = 'off';
      label.append(input);
      return label;
    };

    const botoes = document.createElement('div');
    botoes.className = 'botoes';
    const confirmar = document.createElement('button');
    confirmar.type = 'submit';
    confirmar.textContent = 'Autorizar';
    const cancelar = document.createElement('button');
    cancelar.type = 'button';
    cancelar.className = 'secundario';
    cancelar.textContent = 'Cancelar';
    botoes.append(confirmar, cancelar);

    form.append(titulo, texto, campo('Login do gerente', 'login', 'text'), campo('Senha', 'senha', 'password'), botoes);
    janela.append(form);
    document.body.append(janela);

    const fechar = (valor) => { janela.close(); janela.remove(); resolver(valor); };
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      fechar({ login: form.login.value, senha: form.senha.value });
    });
    cancelar.addEventListener('click', () => fechar(null));
    janela.addEventListener('cancel', () => fechar(null));
    janela.showModal();
  });
}
