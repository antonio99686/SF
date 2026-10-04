const API = {
    base: '../api/',
    
    async req(endpoint, method = 'GET', body = null) {
        const opts = {
            method,
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin'
        };
        if (body) opts.body = JSON.stringify(body);
        
        const res = await fetch(this.base + endpoint, opts);
        const data = await res.json();
        if (!res.ok || data.ok === false) {
            throw new Error(data.erro || 'Erro na requisição');
        }
        return data;
    },

    eventos: {
        listar:   ()      => API.req('eventos.php'),
        criar:    (d)     => API.req('eventos.php', 'POST', d),
        excluir:  (id)    => API.req(`eventos.php?id=${id}`, 'DELETE'),
    },
    mensalidades: {
        listar:  (filtros = {}) => {
            const qs = new URLSearchParams(filtros).toString();
            return API.req('mensalidades.php' + (qs ? '?'+qs : ''));
        },
        criar:   (d)     => API.req('mensalidades.php', 'POST', d),
        pagar:   (id)    => API.req('mensalidades.php', 'PATCH', { id, status: 'pago' }),
    },
    vestimentas: {
        listar:  ()      => API.req('vestimentas.php'),
        entregar:(d)     => API.req('vestimentas.php', 'POST', d),
        devolver:(id)    => API.req(`vestimentas.php?id=${id}`, 'DELETE'),
    },
    usuarios: {
        buscar: (q)      => API.req(`buscar_usuarios.php?q=${encodeURIComponent(q)}`),
    }
};

// Toast de feedback
function toast(msg, tipo = 'ok') {
    const t = document.createElement('div');
    t.className = 'toast ' + tipo;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.classList.add('show'), 10);
    setTimeout(() => { t.classList.remove('show'); setTimeout(()=>t.remove(), 300); }, 3000);
}