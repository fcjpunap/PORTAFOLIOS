<div class="container mt-5">
    <div class="card shadow">
        <div class="card-header bg-primary text-white d-flex justify-content-between">
            <h4>Registro de Ficha Técnica de Caso</h4>
            <button class="btn btn-light btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Imprimir Ficha</button>
        </div>
        <div class="card-body">
            <form id="formFicha">
                <input type="hidden" name="actividad_id" value="1"> <div class="mb-3">
                    <label class="form-label">Integrantes del Grupo (Tú ya estás incluido como líder)</label>
                    <select class="form-control select2" name="integrantes[]" multiple="multiple">
                        <option value="45">Juan Pérez (123456)</option>
                        <option value="46">Ana Gómez (123457)</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">I. Identificación de los Hechos (Factum)</label>
                    <textarea class="form-control" name="factum" rows="3" required></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Subir Anexos (Solo PDF, DOCX, PPTX)</label>
                    <input class="form-control" type="file" name="anexos[]" multiple accept=".pdf,.docx,.pptx">
                </div>

                <button type="submit" class="btn btn-success w-100" id="btnGuardar">
                    <span class="spinner-border spinner-border-sm d-none" id="spinner"></span> Guardar en Portafolio
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Manejo de AJAX con Fetch API
document.getElementById('formFicha').addEventListener('submit', function(e) {
    e.preventDefault();
    
    let formData = new FormData(this);
    let btn = document.getElementById('btnGuardar');
    let spinner = document.getElementById('spinner');
    
    btn.disabled = true;
    spinner.classList.remove('d-none');

    fetch('../api/guardar_ficha.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success') {
            Swal.fire('¡Excelente!', data.message, 'success');
            this.reset();
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    })
    .catch(error => {
        Swal.fire('Error', 'Hubo un problema de conexión', 'error');
    })
    .finally(() => {
        btn.disabled = false;
        spinner.classList.add('d-none');
    });
});
</script>

<style>
@media print {
    body * { visibility: hidden; }
    #formFicha, #formFicha * { visibility: visible; }
    #formFicha { position: absolute; left: 0; top: 0; width: 100%; }
    .btn, input[type=file], .select2 { display: none !important; } /* Ocultar botones al imprimir */
}
</style>