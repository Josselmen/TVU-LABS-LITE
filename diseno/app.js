(function () {
    'use strict';

    var COLORES = { azul: '--azul-institucional', celeste: '--celeste', rojo: '--rojo-acento' };

    var CLASES = {
        ejecutando: 'tk-recoleccion-curso',
        completado: 'tk-recoleccion-ok',
        parcial: 'tk-recoleccion-aviso',
        error: 'tk-recoleccion-error'
    };

    function color(nombre) {
        var variable = COLORES[nombre] || COLORES.azul;
        var valor = getComputedStyle(document.documentElement).getPropertyValue(variable).trim();
        return valor || '#1a3a6b';
    }

    function compacto(valor) {
        return new Intl.NumberFormat('es', { notation: 'compact', maximumFractionDigits: 1 }).format(valor);
    }

    function opciones(mixto) {
        var o = {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: mixto, position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function (item) {
                            return item.dataset.label + ': ' + new Intl.NumberFormat('es').format(item.parsed.y);
                        }
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { maxRotation: 60, autoSkip: true, maxTicksLimit: mixto ? 16 : 12 } },
                y: { beginAtZero: true, ticks: { callback: compacto } }
            }
        };
        if (mixto) {
            o.scales.y1 = { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } };
        }
        return o;
    }

    function construirGrafico(lienzo) {
        var config;
        try {
            config = JSON.parse(lienzo.dataset.grafico);
        } catch (error) {
            return;
        }

        var mixto = config.tipo === 'mixto';
        var series = config.series.map(function (serie) {
            var base = color(serie.color);
            var linea = serie.tipo === 'line';
            return {
                label: serie.nombre,
                data: serie.datos,
                type: serie.tipo || 'bar',
                backgroundColor: linea ? base : base + 'cc',
                borderColor: base,
                borderWidth: linea ? 2 : 0,
                borderRadius: linea ? 0 : 4,
                pointRadius: linea ? 2 : 0,
                tension: 0.3,
                yAxisID: serie.eje || 'y',
                order: linea ? 0 : 1
            };
        });

        new window.Chart(lienzo.getContext('2d'), {
            type: 'bar',
            data: { labels: config.etiquetas, datasets: series },
            options: opciones(mixto)
        });
    }

    function miniaturasRotas() {
        document.querySelectorAll('img.tk-miniatura').forEach(function (imagen) {
            function reemplazar() {
                var marcador = document.createElement('span');
                marcador.className = 'tk-miniatura tk-miniatura-rota';
                imagen.replaceWith(marcador);
            }
            if (imagen.complete && imagen.naturalWidth === 0) {
                reemplazar();
            } else {
                imagen.addEventListener('error', reemplazar, { once: true });
            }
        });
    }

    function confirmaciones() {
        document.addEventListener('click', function (evento) {
            var elemento = evento.target.closest('[data-confirmar]');
            if (elemento && !window.confirm(elemento.getAttribute('data-confirmar'))) {
                evento.preventDefault();
            }
        });
    }

    function pintar(contenedor, datos) {
        var texto = contenedor.querySelector('[data-actualizacion-texto]');
        var boton = contenedor.querySelector('[data-boton-recolectar]');
        var enCurso = datos.estado === 'ejecutando';

        if (texto) {
            texto.textContent = enCurso ? 'Recuperados: ' + datos.recuperados : (datos.mensaje || '');
        }
        if (boton) { boton.disabled = enCurso; }

        Object.keys(CLASES).forEach(function (clave) { contenedor.classList.remove(CLASES[clave]); });
        if (CLASES[datos.estado]) { contenedor.classList.add(CLASES[datos.estado]); }
        contenedor.dataset.estado = datos.estado;
    }

    function seguirActualizacion() {
        var contenedor = document.querySelector('[data-actualizacion]');
        if (!contenedor || contenedor.dataset.estado !== 'ejecutando') {
            return;
        }

        var fallos = 0;

        function consultar() {
            fetch(contenedor.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (respuesta) {
                    if (!respuesta.ok) { throw new Error('estado ' + respuesta.status); }
                    return respuesta.json();
                })
                .then(function (datos) {
                    fallos = 0;
                    pintar(contenedor, datos);

                    if (datos.estado === 'ejecutando') {
                        window.setTimeout(consultar, 3000);
                        return;
                    }

                    var rueda = contenedor.querySelector('.tk-rueda');
                    if (rueda) { rueda.remove(); }
                    window.setTimeout(function () { window.location.reload(); }, 1500);
                })
                .catch(function () {
                    fallos++;
                    if (fallos < 5) { window.setTimeout(consultar, 5000); }
                });
        }

        window.setTimeout(consultar, 2000);
    }

    function autoenviar() {
        document.querySelectorAll('form[data-autoenviar]').forEach(function (formulario) {
            formulario.addEventListener('change', function () { formulario.submit(); });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        confirmaciones();
        miniaturasRotas();
        seguirActualizacion();
        autoenviar();

        if (typeof window.Chart !== 'undefined') {
            document.querySelectorAll('canvas[data-grafico]').forEach(construirGrafico);
        }
    });
})();
