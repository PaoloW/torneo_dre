/**
 * juez.js — UI de la app de jueces (Torneo Macro Regional 2026)
 * Selects encadenados, drag & drop, empates, autoguardado con debounce y auditoría.
 */
(function ($) {
    'use strict';

    var DEBOUNCE = 600; // ms de autoguardado

    var arbol = [];              // árbol desde /api/grupos
    var participantes = [];      // filas del grupo actual
    var seleccion = { disciplina: '', categoria: '', clase: '', prueba: '' };
    var temporizador = null;     // debounce de guardar-orden
    var tempObs = null;          // debounce de observación
    var guardando = false;       // bandera anti-reentrada
    var pendiente = false;       // hubo cambios mientras se guardaba
    var limpiarBandera = false;  // el próximo guardado limpia el grupo
    var SOLO_LECTURA = false;    // F2: vista admin → solo lectura (sin edición)

    /* ---------- utilidades ---------- */
    function esc(valor) {
        return String(valor == null ? '' : valor)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function buscar(id) {
        for (var i = 0; i < participantes.length; i++) {
            if (participantes[i].id === id) return participantes[i];
        }
        return null;
    }

    function nombreCompleto(p) {
        return [p.nombres, p.apepat, p.apemat].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();
    }

    function toast(mensaje, tipo) {
        var $t = $('#toast');
        $t.text(mensaje).removeClass('ok error').addClass('show ' + (tipo || ''));
        clearTimeout($t.data('timer'));
        $t.data('timer', setTimeout(function () { $t.removeClass('show'); }, 1500));
    }

    function mostrarEstado(texto, clase) {
        $('#estado-guardado').text(texto).removeClass('ok error').addClass(clase || '');
    }

    function mensajeError(xhr) {
        if (xhr.responseJSON && xhr.responseJSON.message) return xhr.responseJSON.message;
        if (xhr.status === 419) return 'Sesión expirada, recargue la página';
        return 'Error al guardar (' + xhr.status + ')';
    }

    /* ---------- árbol y selects encadenados ---------- */
    function cargarGrupos() {
        $.getJSON('/api/grupos')
            .done(function (datos) {
                arbol = datos;
                llenarSelect($('#sel-disciplina'), arbol.map(function (d) {
                    return { valor: d.disciplina, texto: d.disciplina + ' (' + d.total + ')' };
                }));
            })
            .fail(function () { toast('No se pudo cargar el árbol de grupos', 'error'); });
    }

    function llenarSelect($select, opciones, etiqueta) {
        $select.empty().append($('<option>').val('').text(etiqueta || 'Seleccione…'));
        opciones.forEach(function (op) {
            $select.append($('<option>').val(op.valor).text(op.texto));
        });
    }

    function nodoDisciplina() {
        return arbol.find(function (d) { return d.disciplina === seleccion.disciplina; }) || null;
    }

    function nodoCategoria() {
        var d = nodoDisciplina();
        return d ? (d.categorias.find(function (c) { return c.categoria === seleccion.categoria; }) || null) : null;
    }

    function nodoClase() {
        var c = nodoCategoria();
        return c ? (c.clases.find(function (l) { return l.clase === seleccion.clase; }) || null) : null;
    }

    /* ---------- selects encadenados ---------- */
    // F1: refresca #sel-prueba según disciplina + clase elegidas.
    // Siempre vacía el select, limpia el valor y lo rellena sin duplicados (se oculta si no hay).
    function refrescarPrueba() {
        seleccion.prueba = '';
        var l = seleccionCompleta() ? nodoClase() : null;
        var pruebas = [];
        if (l && l.pruebas) {
            l.pruebas.forEach(function (p) {
                if (p && pruebas.indexOf(p) === -1) pruebas.push(p); // sin duplicados
            });
        }
        var opciones = pruebas.map(function (p) { return { valor: p, texto: p }; });
        llenarSelect($('#sel-prueba'), opciones, 'Todas');
        $('#sel-prueba').val('');
        $('#campo-prueba').prop('hidden', !opciones.length);
    }

    // F11: llena #sel-clase según la categoría elegida y, si solo hay 1 clase, la elige por defecto
    function refrescarClase() {
        seleccion.clase = '';
        $('#campo-clase').prop('hidden', true);
        llenarSelect($('#sel-clase'), []);
        var c = nodoCategoria();
        if (!c) return;
        llenarSelect($('#sel-clase'), c.clases.map(function (l) {
            return { valor: l.clase, texto: l.clase + ' (' + l.total + ')' };
        }));
        $('#campo-clase').prop('hidden', false);
        if (c.clases.length === 1) { // F11: única clase → elegida por defecto
            seleccion.clase = c.clases[0].clase;
            $('#sel-clase').val(seleccion.clase);
        }
    }

    function alCambiarDisciplina() {
        seleccion.disciplina = $('#sel-disciplina').val() || '';
        seleccion.categoria = ''; seleccion.clase = '';
        $('#campo-categoria').prop('hidden', true);
        llenarSelect($('#sel-categoria'), []);
        refrescarClase(); // F11: limpia la clase heredada
        if (seleccion.disciplina) {
            var d = nodoDisciplina();
            llenarSelect($('#sel-categoria'), d.categorias.map(function (c) {
                return { valor: c.categoria, texto: c.categoria + ' (' + c.total + ')' };
            }));
            $('#campo-categoria').prop('hidden', false);
            if (d.categorias.length === 1) { // F11: única categoría → elegida por defecto
                seleccion.categoria = d.categorias[0].categoria;
                $('#sel-categoria').val(seleccion.categoria);
                refrescarClase(); // carga las clases y auto-elige si hay una sola
            }
        }
        refrescarPrueba();
        renderMigas(); cargarLista();
    }

    function alCambiarCategoria() {
        seleccion.categoria = $('#sel-categoria').val() || '';
        refrescarClase(); // F11: auto-selecciona la clase si solo hay 1 y limpia si hay varias
        refrescarPrueba();
        renderMigas(); cargarLista();
    }

    function alCambiarClase() {
        seleccion.clase = $('#sel-clase').val() || '';
        refrescarPrueba();
        renderMigas(); cargarLista();
    }

    function alCambiarPrueba() {
        seleccion.prueba = $('#sel-prueba').val() || '';
        renderMigas(); cargarLista();
    }

    function seleccionCompleta() {
        return seleccion.disciplina && seleccion.categoria && seleccion.clase;
    }

    function renderMigas() {
        var niveles = ['Disciplina', 'Categoría', 'Clase', 'Prueba'];
        var valores = [seleccion.disciplina, seleccion.categoria, seleccion.clase, seleccion.prueba];
        var ultimo = 0;
        valores.forEach(function (v, i) { if (v) ultimo = i; });
        var html = '';
        valores.forEach(function (v, i) {
            if (i > 0) html += '<span class="separador">/</span>';
            if (i === ultimo && v) html += '<span class="actual">' + esc(v) + '</span>';
            else html += '<span>' + esc(v || niveles[i]) + '</span>';
        });
        $('#migas').html(html);
    }

    /* ---------- carga y render de la lista ---------- */
    function cargarLista() {
        if (!seleccionCompleta()) {
            participantes = [];
            $('#observacion').val('');
            render();
            return;
        }
        $.getJSON('/api/participantes', seleccion)
            .done(function (data) {
                participantes = data.participantes || [];
                $('#observacion').val(data.observacion || '');
                render();
            })
            .fail(function (xhr) { toast(mensajeError(xhr), 'error'); });
    }

    function contarPuestos() {
        var conteos = {};
        participantes.forEach(function (p) {
            if (p.puesto == null) return;
            conteos[p.puesto] = (conteos[p.puesto] || 0) + 1;
        });
        return conteos;
    }

    function badgePuesto(p, conteos) {
        if (p.puesto == null) return '<span class="badge-puesto vacio">—</span>';
        var empate = conteos[p.puesto] > 1;
        return '<span class="badge-puesto' + (empate ? ' empate' : '') + '"' +
            (empate ? ' title="Empate"' : '') + '>' + p.puesto + '°' +
            (empate ? '<small>empate</small>' : '') + '</span>';
    }

    function opcionesPuesto(p, total) {
        var html = '<option value="">—</option>';
        for (var i = 1; i <= total; i++) {
            html += '<option value="' + i + '"' + (p.puesto === i ? ' selected' : '') + '>' + i + '°</option>';
        }
        return html;
    }

    // F12: con más de 5 puestos se usa un combo con filtro al tipear (en vez del <select> nativo)
    function comboPuesto(p, total, nombre) {
        var etiqueta = p.puesto == null ? '—' : p.puesto + '°';
        var items = '<li data-valor=""' + (p.puesto == null ? ' class="activa"' : '') + '>— (sin puesto)</li>';
        for (var i = 1; i <= total; i++) {
            items += '<li data-valor="' + i + '"' + (p.puesto === i ? ' class="activa"' : '') + '>' + i + '°</li>';
        }
        return '<div class="combo-puesto">' +
            '<button type="button" class="combo-btn" aria-haspopup="listbox" aria-expanded="false" aria-label="Puesto de ' + esc(nombre) + '">' + etiqueta + '</button>' +
            '<div class="combo-panel" hidden>' +
                '<input type="text" class="combo-filtro" placeholder="Filtrar puesto…" autocomplete="off" aria-label="Filtrar puesto">' +
                '<ul class="combo-lista" role="listbox">' + items + '</ul>' +
                '<p class="combo-vacio" hidden>Sin coincidencias</p>' +
            '</div>' +
        '</div>';
    }

    function filaHtml(p, total, conteos) {
        var dni = p.numdoc_participante ? String(p.numdoc_participante).trim() : ''; // F7
        var nombre = nombreCompleto(p);
        var meta = [p.ie, p.ugel, p.regionie].filter(Boolean).join(' · ');
        var nivel = [p.nivel_educativo, p.grado].filter(Boolean).join(' · '); // F9: Primaria/Secundaria + grado
        var extra = p.dsc_prueba ? [p.dsc_prueba, p.metrica, p.marca].filter(Boolean).join(' · ') : '';
        var textoBuscar = (dni + ' ' + nombre + ' ' + meta).toLowerCase(); // F7: incluye DNI

        // F2: en solo lectura no se pintan select de puesto, flechas ni handle de arrastre
        var controles = '<button type="button" class="btn-icono btn-historial" aria-label="Historial" title="Historial">🕘</button>';
        if (!SOLO_LECTURA) {
            // F12: >5 puestos → combo con filtro; ≤5 puestos → select nativo
            var controlPuesto = total > 5
                ? comboPuesto(p, total, nombre)
                : '<select class="sel-puesto" aria-label="Puesto de ' + esc(nombre) + '">' + opcionesPuesto(p, total) + '</select>';
            controles = controlPuesto +
                controles +
                '<div class="movs">' +
                    '<button type="button" class="btn-icono btn-subir" aria-label="Subir">▲</button>' +
                    '<button type="button" class="btn-icono btn-bajar" aria-label="Bajar">▼</button>' +
                '</div>';
        }
        var handle = SOLO_LECTURA ? '' : '<span class="handle" title="Arrastrar" aria-hidden="true">☰</span>';

        return '<li class="fila' + (p.puesto == null ? ' sin-puesto' : '') + '" data-id="' + p.id +
            '" data-buscar="' + esc(textoBuscar) + '">' +
            badgePuesto(p, conteos) +
            '<div class="fila-info">' +
                '<div class="fila-nombre">' + (dni ? '<span class="fila-dni">' + esc(dni) + '</span> ' : '') + esc(nombre) + '</div>' +
                '<div class="fila-meta">' + esc(meta) + '</div>' +
                (nivel ? '<div class="fila-meta fila-nivel">' + esc(nivel) + '</div>' : '') +
                (extra ? '<div class="fila-meta">' + esc(extra) + '</div>' : '') +
            '</div>' +
            '<div class="fila-controles">' + controles + '</div>' +
            handle +
        '</li>';
    }

    function render() {
        var $lista = $('#lista');
        var $vacio = $('#vacio');
        $lista.empty();

        if (!seleccionCompleta()) {
            $vacio.text('Seleccione disciplina, categoría y clase para ver los clasificados.').prop('hidden', false);
        } else if (!participantes.length) {
            $vacio.text('No hay clasificados en este grupo.').prop('hidden', false);
        } else {
            $vacio.prop('hidden', true);
            var total = participantes.length;
            var conteos = contarPuestos();
            var html = '';
            participantes.forEach(function (p) { html += filaHtml(p, total, conteos); });
            $lista.html(html);
        }

        $('#badge-clasificados').text(participantes.length + ' clasificados').prop('hidden', !participantes.length);
        $('#btn-limpiar').prop('disabled', !participantes.length);
        $('#btn-pdf').prop('disabled', !seleccionCompleta()); // F3: export del grupo filtrado
        $('#panel-observacion').prop('hidden', !seleccionCompleta());

        aplicarBuscador();
        if ($lista.data('ui-sortable')) $lista.sortable('refresh');
    }

    /* ---------- orden local y autoguardado ---------- */
    function ordenarLocal() {
        participantes.sort(function (a, b) {
            if (a.puesto == null && b.puesto == null) return a.id - b.id;
            if (a.puesto == null) return 1;          // sin puesto → al final
            if (b.puesto == null) return -1;
            if (a.puesto !== b.puesto) return a.puesto - b.puesto;
            return a.id - b.id;                      // empates → por id
        });
    }

    function programarGuardado() {
        if (SOLO_LECTURA) return; // F2: la vista admin no edita
        clearTimeout(temporizador);
        temporizador = setTimeout(guardarOrden, DEBOUNCE);
    }

    function guardarOrden() {
        if (SOLO_LECTURA) return; // F2
        if (!seleccionCompleta()) return;
        if (guardando) { pendiente = true; return; } // anti-reentrada: se reprograma

        guardando = true;
        mostrarEstado('Guardando…');

        var items = participantes
            .filter(function (p) { return p.puesto != null; })
            .map(function (p) { return { id: p.id, puesto: p.puesto }; });
        var limpiar = limpiarBandera;
        limpiarBandera = false;

        $.ajax({
            url: '/api/guardar-orden',
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: JSON.stringify({ grupo: seleccion, items: items, limpiar: limpiar })
        })
            .done(function () {
                if (!pendiente) {
                    toast('Guardado ✓', 'ok');
                    mostrarEstado('Guardado ✓', 'ok');
                }
            })
            .fail(function (xhr) {
                toast(mensajeError(xhr), 'error');
                mostrarEstado('Error al guardar', 'error');
            })
            .always(function () {
                guardando = false;
                if (pendiente) {
                    pendiente = false;
                    guardarOrden(); // reprograma el guardado pendiente
                }
            });
    }

    /* ---------- drag & drop (jQuery UI Sortable + touch-punch) ---------- */
    function recalcularDesdeDom() {
        var ids = $('#lista').children('.fila').map(function () {
            return Number($(this).data('id'));
        }).get();
        var reordenados = [];
        ids.forEach(function (id, i) {
            var p = buscar(id);
            if (!p) return;
            p.puesto = i + 1; // el orden visual define los puestos
            reordenados.push(p);
        });
        participantes = reordenados;
        render();
        programarGuardado();
    }

    function moverFila(id, delta) {
        if (SOLO_LECTURA) return; // F2
        var idx = participantes.findIndex(function (p) { return p.id === id; });
        var destino = idx + delta;
        if (idx < 0 || destino < 0 || destino >= participantes.length) return;
        var tmp = participantes[idx];
        participantes[idx] = participantes[destino];
        participantes[destino] = tmp;
        participantes.forEach(function (p, i) { p.puesto = i + 1; });
        render();
        programarGuardado();
    }

    /* ---------- eventos de la lista ---------- */
    // F12: cierra todos los combos de puesto abiertos
    function cerrarCombos() {
        $('.combo-panel').each(function () {
            if (!$(this).prop('hidden')) {
                $(this).prop('hidden', true);
                $(this).closest('.combo-puesto').find('.combo-btn').attr('aria-expanded', 'false');
            }
        });
    }

    function eventosLista() {
        $('#lista').on('change', '.sel-puesto', function () {
            if (SOLO_LECTURA) return; // F2
            var id = Number($(this).closest('.fila').data('id'));
            var p = buscar(id);
            if (!p) return;
            var valor = $(this).val();
            p.puesto = valor === '' ? null : Number(valor); // empates: se escribe tal cual
            ordenarLocal();
            render();
            programarGuardado();
        });

        // F12: combo de puesto (>5 puestos) — abrir/cierra
        $('#lista').on('click', '.combo-btn', function (e) {
            e.stopPropagation();
            var $panel = $(this).siblings('.combo-panel');
            var abrir = $panel.prop('hidden');
            cerrarCombos();
            $panel.prop('hidden', !abrir);
            $(this).attr('aria-expanded', String(abrir));
            if (abrir) $panel.find('.combo-filtro').val('').trigger('input').focus();
        });

        // F12: filtra las opciones del combo al tipear (subcadena, p. ej. "1" → 1°, 10°, 11°…)
        $('#lista').on('input', '.combo-filtro', function () {
            var texto = String($(this).val() || '').replace('°', '').toLowerCase().trim();
            var $panel = $(this).closest('.combo-panel');
            var visibles = 0;
            $panel.find('.combo-lista li').each(function () {
                var coincide = !texto || $(this).text().replace('°', '').toLowerCase().indexOf(texto) !== -1;
                $(this).prop('hidden', !coincide);
                if (coincide) visibles++;
            });
            $panel.find('.combo-vacio').prop('hidden', visibles > 0); // "Sin coincidencias"
        });

        // F12: Enter elige la primera opción visible; Esc cierra el combo
        $('#lista').on('keydown', '.combo-filtro', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var $primero = $(this).closest('.combo-panel').find('.combo-lista li:not([hidden])').first();
                if ($primero.length) $primero.trigger('click');
            } else if (e.key === 'Escape') {
                cerrarCombos();
            }
        });

        // F12: elegir un puesto desde el combo (mismas reglas que el select: empates tal cual)
        $('#lista').on('click', '.combo-lista li', function (e) {
            e.stopPropagation();
            if (SOLO_LECTURA) return; // F2
            var id = Number($(this).closest('.fila').data('id'));
            var p = buscar(id);
            if (!p) return;
            var valor = String($(this).attr('data-valor') || '');
            p.puesto = valor === '' ? null : Number(valor);
            ordenarLocal();
            render();
            programarGuardado();
        });

        $('#lista').on('click', '.btn-subir', function () {
            moverFila(Number($(this).closest('.fila').data('id')), -1);
        });

        $('#lista').on('click', '.btn-bajar', function () {
            moverFila(Number($(this).closest('.fila').data('id')), 1);
        });

        $('#lista').on('click', '.btn-historial', function () {
            abrirHistorial(Number($(this).closest('.fila').data('id')));
        });
    }

    function iniciarSortable() {
        if (SOLO_LECTURA) return; // F2: sin drag & drop en la vista admin
        $('#lista').sortable({
            handle: '.handle',
            delay: 200,
            touchStartThreshold: 5,
            axis: 'y',
            update: function () { recalcularDesdeDom(); }
        });
    }

    /* ---------- buscador local (DNI, nombre o IE) ---------- */
    function aplicarBuscador() {
        var texto = ($('#buscador').val() || '').toLowerCase().trim();
        $('#btn-limpiar-buscador').prop('hidden', !texto); // F8: ✕ solo si hay texto
        $('#lista .fila').each(function () {
            var coincide = !texto || String($(this).data('buscar')).indexOf(texto) !== -1;
            $(this).toggle(coincide);
        });
    }

    /* ---------- observación (mismo debounce, upsert) ---------- */
    function programarObservacion() {
        if (SOLO_LECTURA) return; // F2: observación en solo lectura
        clearTimeout(tempObs);
        tempObs = setTimeout(guardarObservacion, DEBOUNCE);
    }

    function guardarObservacion() {
        if (!seleccionCompleta()) return;
        $.ajax({
            url: '/api/guardar-observacion',
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: JSON.stringify({ grupo: seleccion, texto: $('#observacion').val() })
        })
            .done(function () { toast('Observación guardada ✓', 'ok'); })
            .fail(function (xhr) { toast(mensajeError(xhr), 'error'); });
    }

    /* ---------- limpiar orden ---------- */
    function limpiarOrden() {
        if (SOLO_LECTURA) return; // F2
        if (!participantes.length) return;
        if (!confirm('¿Limpiar el orden del grupo actual? Los puestos quedarán vacíos.')) return;
        participantes.forEach(function (p) { p.puesto = null; });
        ordenarLocal();
        render();
        clearTimeout(temporizador);
        limpiarBandera = true;
        guardarOrden(); // envía items: [] de inmediato
    }

    /* ---------- historial de puesto (modal, solo lectura) ---------- */
    function fechaCorta(valor) {
        if (!valor) return '—';
        var partes = String(valor).split(' ');
        var fecha = partes[0].split('-');
        if (fecha.length !== 3) return valor;
        return fecha[2] + '/' + fecha[1] + '/' + fecha[0] + (partes[1] ? ' ' + partes[1].substring(0, 5) : '');
    }

    function textoPuesto(valor) {
        return valor == null ? '—' : valor + '°';
    }

    function abrirHistorial(id) {
        var p = buscar(id);
        $('#historial-nombre').text(p ? nombreCompleto(p) : '');
        $('#historial-cuerpo').empty();
        $('#historial-vacio').prop('hidden', true);

        $.getJSON('/api/historial/' + id)
            .done(function (filas) {
                var html = '';
                filas.forEach(function (f) {
                    html += '<tr>' +
                        '<td>' + esc(fechaCorta(f.created_at)) + '</td>' +
                        '<td>' + esc(f.user_nombre || '—') + '</td>' +
                        '<td>' + textoPuesto(f.puesto_anterior) + ' → ' + textoPuesto(f.puesto_nuevo) + '</td>' +
                    '</tr>';
                });
                $('#historial-cuerpo').html(html);
                $('#historial-vacio').prop('hidden', filas.length > 0);
                var dlg = document.getElementById('modal-historial');
                if (dlg.showModal) dlg.showModal();
                else dlg.setAttribute('open', '');
            })
            .fail(function (xhr) { toast(mensajeError(xhr), 'error'); });
    }

    /* ---------- init ---------- */
    function iniciar() {
        // F2: detecta la vista admin (solo lectura) antes de montar la UI
        SOLO_LECTURA = String($('#vista-resultados').data('soloLectura')) === 'true';
        if (SOLO_LECTURA) {
            $('#btn-limpiar').prop('hidden', true);
            $('#observacion').prop('readonly', true);
        }

        cargarGrupos();
        iniciarSortable();
        eventosLista();

        $('#sel-disciplina').on('change', alCambiarDisciplina);
        $('#sel-categoria').on('change', alCambiarCategoria);
        $('#sel-clase').on('change', alCambiarClase);
        $('#sel-prueba').on('change', alCambiarPrueba);
        // F12: el combo de puesto se cierra al pulsar fuera de él (el filtro no lo cierra)
        $(document).on('click', function (e) {
            if ($(e.target).closest('.combo-puesto').length) return;
            cerrarCombos();
        });
        $('#buscador').on('input', aplicarBuscador);
        $('#btn-limpiar-buscador').on('click', function () { // F8: limpia la búsqueda
            $('#buscador').val('').trigger('input').focus();
        });
        $('#btn-limpiar').on('click', limpiarOrden);
        // F3: exporta el grupo filtrado en una pestaña nueva (preview web → Imprimir / Guardar PDF)
        $('#btn-pdf').on('click', function () {
            if (!seleccionCompleta()) return;
            window.open('/resultados/pdf?' + $.param(seleccion), '_blank');
        });
        $('#observacion').on('input', programarObservacion);
        $('#btn-cerrar-historial').on('click', function () {
            document.getElementById('modal-historial').close();
        });

        renderMigas();
        render();
    }

    $(document).ready(iniciar);
})(jQuery);
