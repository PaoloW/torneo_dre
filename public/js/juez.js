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

    function alCambiarDisciplina() {
        seleccion.disciplina = $('#sel-disciplina').val() || '';
        seleccion.categoria = ''; seleccion.clase = '';
        $('#campo-categoria, #campo-clase').prop('hidden', true);
        llenarSelect($('#sel-categoria'), []);
        llenarSelect($('#sel-clase'), []);
        if (seleccion.disciplina) {
            var d = nodoDisciplina();
            llenarSelect($('#sel-categoria'), d.categorias.map(function (c) {
                return { valor: c.categoria, texto: c.categoria + ' (' + c.total + ')' };
            }));
            $('#campo-categoria').prop('hidden', false);
        }
        refrescarPrueba();
        renderMigas(); cargarLista();
    }

    function alCambiarCategoria() {
        seleccion.categoria = $('#sel-categoria').val() || '';
        seleccion.clase = '';
        $('#campo-clase').prop('hidden', true);
        llenarSelect($('#sel-clase'), []);
        if (seleccion.categoria) {
            var c = nodoCategoria();
            llenarSelect($('#sel-clase'), c.clases.map(function (l) {
                return { valor: l.clase, texto: l.clase + ' (' + l.total + ')' };
            }));
            $('#campo-clase').prop('hidden', false);
        }
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

    function filaHtml(p, total, conteos) {
        var nombre = nombreCompleto(p);
        var meta = [p.ie, p.ugel, p.regionie].filter(Boolean).join(' · ');
        var extra = p.dsc_prueba ? [p.dsc_prueba, p.metrica, p.marca].filter(Boolean).join(' · ') : '';
        var textoBuscar = (nombre + ' ' + meta).toLowerCase();

        return '<li class="fila' + (p.puesto == null ? ' sin-puesto' : '') + '" data-id="' + p.id +
            '" data-buscar="' + esc(textoBuscar) + '">' +
            badgePuesto(p, conteos) +
            '<div class="fila-info">' +
                '<div class="fila-nombre">' + esc(nombre) + '</div>' +
                '<div class="fila-meta">' + esc(meta) + '</div>' +
                (extra ? '<div class="fila-meta">' + esc(extra) + '</div>' : '') +
            '</div>' +
            '<div class="fila-controles">' +
                '<select class="sel-puesto" aria-label="Puesto de ' + esc(nombre) + '">' + opcionesPuesto(p, total) + '</select>' +
                '<button type="button" class="btn-icono btn-historial" aria-label="Historial" title="Historial">🕘</button>' +
                '<div class="movs">' +
                    '<button type="button" class="btn-icono btn-subir" aria-label="Subir">▲</button>' +
                    '<button type="button" class="btn-icono btn-bajar" aria-label="Bajar">▼</button>' +
                '</div>' +
            '</div>' +
            '<span class="handle" title="Arrastrar" aria-hidden="true">☰</span>' +
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
        clearTimeout(temporizador);
        temporizador = setTimeout(guardarOrden, DEBOUNCE);
    }

    function guardarOrden() {
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
    function eventosLista() {
        $('#lista').on('change', '.sel-puesto', function () {
            var id = Number($(this).closest('.fila').data('id'));
            var p = buscar(id);
            if (!p) return;
            var valor = $(this).val();
            p.puesto = valor === '' ? null : Number(valor); // empates: se escribe tal cual
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
        $('#lista').sortable({
            handle: '.handle',
            delay: 200,
            touchStartThreshold: 5,
            axis: 'y',
            update: function () { recalcularDesdeDom(); }
        });
    }

    /* ---------- buscador local (nombre o IE) ---------- */
    function aplicarBuscador() {
        var texto = ($('#buscador').val() || '').toLowerCase().trim();
        $('#lista .fila').each(function () {
            var coincide = !texto || String($(this).data('buscar')).indexOf(texto) !== -1;
            $(this).toggle(coincide);
        });
    }

    /* ---------- observación (mismo debounce, upsert) ---------- */
    function programarObservacion() {
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
        cargarGrupos();
        iniciarSortable();
        eventosLista();

        $('#sel-disciplina').on('change', alCambiarDisciplina);
        $('#sel-categoria').on('change', alCambiarCategoria);
        $('#sel-clase').on('change', alCambiarClase);
        $('#sel-prueba').on('change', alCambiarPrueba);
        $('#buscador').on('input', aplicarBuscador);
        $('#btn-limpiar').on('click', limpiarOrden);
        $('#observacion').on('input', programarObservacion);
        $('#btn-cerrar-historial').on('click', function () {
            document.getElementById('modal-historial').close();
        });

        renderMigas();
        render();
    }

    $(document).ready(iniciar);
})(jQuery);
