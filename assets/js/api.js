/* ============================================================
   PIQUETE SENTINELA DA FRONTEIRA · API + Helpers Front-end
   ------------------------------------------------------------
   - Cliente HTTP com CSRF automático
   - Endpoints da API REST (eventos, mensalidades, vestimentas, usuários)
   - Máscaras de CPF/Telefone
   - Força de senha
   - Validações de formulário
   - Toast de feedback
   - Helpers de formatação (moeda, data, CPF)
   ============================================================ */

/* ============================================================
   1) CLIENTE DA API
   ============================================================ */
const API = {
    base: '../api/',
    csrfToken: null,

    /**
     * Inicializa capturando o token CSRF da <meta name="csrf-token">
     * Chame automaticamente no DOMContentLoaded.
     */
    init() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) this.csrfToken = meta.content;
    },

    /**
     * Requisição genérica
     */
    async req(endpoint, method = 'GET', body = null) {
        const opts = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.csrfToken || ''
            },
            credentials: 'same-origin'
        };
        if (body) opts.body = JSON.stringify(body);

        let res, data;
        try {
            res  = await fetch(this.base + endpoint, opts);
            data = await res.json();
        } catch (err) {
            throw new Error('Falha na comunicação com o servidor.');
        }

        if (!res.ok || data.ok === false) {
            throw new Error(data?.erro || `Erro ${res.status}`);
        }
        return data;
    },

    /* ---------- EVENTOS ---------- */
    eventos: {
        listar:  ()   => API.req('eventos.php'),
        criar:   (d)  => API.req('eventos.php', 'POST', d),
        excluir: (id) => API.req(`eventos.php?id=${id}`, 'DELETE'),
    },

    /* ---------- MENSALIDADES ---------- */
    mensalidades: {
        listar: (filtros = {}) => {
            const qs = new URLSearchParams(filtros).toString();
            return API.req('mensalidades.php' + (qs ? '?' + qs : ''));
        },
        criar:  (d)  => API.req('mensalidades.php', 'POST', d),
        pagar:  (id) => API.req('mensalidades.php', 'PATCH', { id, status: 'pago' }),
        mudarStatus: (id, status) => API.req('mensalidades.php', 'PATCH', { id, status }),
    },

    /* ---------- VESTIMENTAS ---------- */
    vestimentas: {
        listar:  ()   => API.req('vestimentas.php'),
        entregar:(d)  => API.req('vestimentas.php', 'POST', d),
        devolver:(id) => API.req(`vestimentas.php?id=${id}`, 'DELETE'),
    },

    /* ---------- USUÁRIOS ---------- */
    usuarios: {
        buscar: (q) => API.req(`buscar_usuarios.php?q=${encodeURIComponent(q)}`),
    },
};

/* ============================================================
   2) TOAST DE FEEDBACK
   ============================================================ */
function toast(msg, tipo = 'ok', duracao = 3200) {
    // Remove toasts antigos empilhados
    document.querySelectorAll('.toast').forEach(t => {
        if (t.dataset.auto === '1') t.remove();
    });

    const t = document.createElement('div');
    t.className = 'toast ' + tipo;
    t.dataset.auto = '1';
    t.innerHTML = (tipo === 'ok' ? '✅ ' : tipo === 'erro' ? '⚠️ ' : 'ℹ️ ') + msg;
    document.body.appendChild(t);

    requestAnimationFrame(() => t.classList.add('show'));
    setTimeout(() => {
        t.classList.remove('show');
        setTimeout(() => t.remove(), 300);
    }, duracao);
}

/* ============================================================
   3) MÁSCARAS
   ============================================================ */
function aplicarMascaraCPF(el) {
    el.addEventListener('input', e => {
        let v = e.target.value.replace(/\D/g, '').slice(0, 11);
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        e.target.value = v;
    });
}

function aplicarMascaraTelefone(el) {
    el.addEventListener('input', e => {
        let v = e.target.value.replace(/\D/g, '').slice(0, 11);
        if (v.length > 10) {
            v = v.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
        } else if (v.length > 6) {
            v = v.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
        } else if (v.length > 2) {
            v = v.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
        } else {
            v = v.replace(/^(\d*)/, '($1');
        }
        e.target.value = v;
    });
}

function aplicarMascaraCEP(el) {
    el.addEventListener('input', e => {
        let v = e.target.value.replace(/\D/g, '').slice(0, 8);
        v = v.replace(/^(\d{5})(\d)/, '$1-$2');
        e.target.value = v;
    });
}

function aplicarMascaras(root = document) {
    root.querySelectorAll('[data-mask="cpf"]').forEach(aplicarMascaraCPF);
    root.querySelectorAll('[data-mask="tel"]').forEach(aplicarMascaraTelefone);
    root.querySelectorAll('[data-mask="cep"]').forEach(aplicarMascaraCEP);
}

/* ============================================================
   4) FORÇA DA SENHA
   ============================================================ */
function calcularForcaSenha(senha) {
    if (!senha) return { score: 0, label: 'vazia', cor: '#e5e7eb', largura: '0%' };

    let score = 0;
    if (senha.length >= 6)  score++;
    if (senha.length >= 10) score++;
    if (/[A-Z]/.test(senha)) score++;
    if (/[0-9]/.test(senha)) score++;
    if (/[^A-Za-z0-9]/.test(senha)) score++;

    const mapa = [
        { label: 'muito fraca', cor: '#dc2626', largura: '20%'  },
        { label: 'fraca',       cor: '#f59e0b', largura: '40%'  },
        { label: 'média',       cor: '#f59e0b', largura: '60%'  },
        { label: 'forte',       cor: '#16a34a', largura: '80%'  },
        { label: 'muito forte', cor: '#009739', largura: '100%' },
    ];
    const idx = Math.max(0, Math.min(4, score - 1));
    return { score, ...mapa[idx] };
}

function aplicarForcaSenha(input, barra) {
    if (!input || !barra) return;
    input.addEventListener('input', e => {
        const f = calcularForcaSenha(e.target.value);
        barra.style.setProperty('--w', e.target.value ? f.largura : '0%');
        barra.style.setProperty('--c', f.cor);
        barra.title = 'Força: ' + f.label;
    });
}

/* ============================================================
   5) VALIDAÇÃO DE CPF (front-end, espelha o PHP)
   ============================================================ */
function validarCPF(cpf) {
    cpf = String(cpf).replace(/\D/g, '');
    if (cpf.length !== 11) return false;
    if (/^(\d)\1{10}$/.test(cpf)) return false;

    for (let t = 9; t < 11; t++) {
        let soma = 0;
        for (let i = 0; i < t; i++) {
            soma += parseInt(cpf[i]) * ((t + 1) - i);
        }
        const digito = ((10 * soma) % 11) % 10;
        if (parseInt(cpf[t]) !== digito) return false;
    }
    return true;
}

function validarEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function validarTelefone(tel) {
    const t = String(tel).replace(/\D/g, '');
    return t.length === 10 || t.length === 11;
}

/* ============================================================
   6) HELPERS DE FORMATAÇÃO
   ============================================================ */
function formatarMoeda(valor) {
    return 'R$ ' + Number(valor || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function formatarData(data, incluirHora = false) {
    if (!data) return '—';
    const d = new Date(data);
    if (isNaN(d)) return data;
    const opts = { day: '2-digit', month: '2-digit', year: 'numeric' };
    if (incluirHora) { opts.hour = '2-digit'; opts.minute = '2-digit'; }
    return d.toLocaleString('pt-BR', opts);
}

function formatarCPF(cpf) {
    cpf = String(cpf).replace(/\D/g, '');
    if (cpf.length !== 11) return cpf;
    return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
}

function calcularIdade(dataNasc) {
    if (!dataNasc) return null;
    const hoje = new Date();
    const dn = new Date(dataNasc);
    if (isNaN(dn)) return null;
    let idade = hoje.getFullYear() - dn.getFullYear();
    const m = hoje.getMonth() - dn.getMonth();
    if (m < 0 || (m === 0 && hoje.getDate() < dn.getDate())) idade--;
    return idade;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

/* ============================================================
   7) CSRF AUTOMÁTICO EM FORMULÁRIOS
   ------------------------------------------------------------
   Para forms normais (não AJAX), o PHP já insere o campo.
   Esta função garante que, se por acaso faltar, seja adicionado.
   ============================================================ */
function garantirCsrfEmForms() {
    const token = API.csrfToken;
    if (!token) return;
    document.querySelectorAll('form[method="POST"], form[method="post"]').forEach(form => {
        if (form.dataset.noCsrf === '1') return;
        if (!form.querySelector('input[name="csrf_token"]')) {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'csrf_token';
            inp.value = token;
            form.prepend(inp);
        }
    });
}

/* ============================================================
   8) BLOQUEIO DE BOTÃO DURANTE SUBMIT
   ============================================================ */
function bloquearBotaoAteSubmit() {
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', () => {
            const btn = form.querySelector('button[type="submit"]');
            if (btn && !btn.disabled) {
                setTimeout(() => {
                    btn.disabled = true;
                    const original = btn.innerHTML;
                    btn.innerHTML = '⏳ Enviando...';
                    // Reabilita caso o form não navegue (ex: erro de JS)
                    setTimeout(() => {
                        btn.disabled = false;
                        btn.innerHTML = original;
                    }, 8000);
                }, 10);
            }
        });
    });
}

/* ============================================================
   9) AUTO-INICIALIZAÇÃO
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
    API.init();
    aplicarMascaras();
    garantirCsrfEmForms();
    bloquearBotaoAteSubmit();

    // Força de senha em inputs comuns
    const senhaInput = document.querySelector('input[name="senha"]');
    const forcaBar   = document.getElementById('forca');
    if (senhaInput && forcaBar) aplicarForcaSenha(senhaInput, forcaBar);
});

/* ============================================================
   10) EXPOR GLOBALMENTE (para uso inline em <script>)
   ============================================================ */
window.API            = API;
window.toast          = toast;
window.aplicarMascaras = aplicarMascaras;
window.validarCPF     = validarCPF;
window.validarEmail   = validarEmail;
window.validarTelefone= validarTelefone;
window.formatarMoeda  = formatarMoeda;
window.formatarData   = formatarData;
window.formatarCPF    = formatarCPF;
window.calcularIdade  = calcularIdade;
window.escapeHtml     = escapeHtml;
window.calcularForcaSenha = calcularForcaSenha;