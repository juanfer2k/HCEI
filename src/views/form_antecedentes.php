<section id="antecedentes" class="form-step" data-step="9">
    <div class="form-container form-section antecedentes">
        <div class="section-header">
          <h3>Antecedentes Médicos <span class="hl7-tag">FHIR: Condition, AllergyIntolerance</span></h3>
        </div>
        <?php
          $opciones_antecedentes = [
              'Patologicos' => ['Hipertension Arterial (HTA)', 'Diabetes Mellitus (DM)', 'Enfermedad Cardiovascular (ECV)', 'EPOC', 'Asma', 'Cancer', 'Enfermedad Renal Cronica (ERC)', 'Convulsiones / Epilepsia', 'Otro'],
              'Alergicos' => ['Medicamentos (AINES, Penicilina)', 'Alimentos (Mani, Mariscos)', 'Latex', 'Picaduras de insectos', 'Polen / Ambientales', 'Otro'],
              'Quirurgicos' => ['Apendicectomia', 'Colecistectomia', 'Cesarea', 'Cirugia Cardiaca', 'Cirugia Ortopedica', 'Otro'],
              'Traumatologicos' => ['Fracturas', 'Esguinces', 'TEC (Traumatismo)', 'Heridas por arma', 'Otro'],
              'Toxicologicos' => ['Alcohol', 'Tabaco', 'Sustancias Psicoactivas (SPA)', 'Intoxicacion por medicamentos', 'Intoxicacion por quimicos', 'Otro'],
              'Familiares' => ['Hipertension Arterial (HTA)', 'Diabetes Mellitus (DM)', 'Cancer', 'Enfermedad Cardiovascular', 'Otro']
          ];
          $antecedentes = [
            "Patologicos" => "Patologicos",
            "Alergicos" => "Alergicos",
            "Quirurgicos" => "Quirurgicos",
            "Traumatologicos" => "Traumatologicos",
            "Toxicologicos" => "Toxicologicos",
            "GinecoObstetricos" => "Gineco-Obstetricos",
            "Familiares" => "Familiares"
          ];
          foreach ($antecedentes as $key => $label) {
            $key_lower = strtolower($key);
        ?>
        <div class="row mb-2 align-items-center" id="row_ant_<?= $key_lower ?>">
          <div class="col-md-3">
            <label class="form-label"><?= $label ?></label>
          </div>
          <div class="col-md-2">
            <select class="form-select ant-select" name="ant_<?= $key_lower ?>_sn" id="ant_<?= $key_lower ?>_sn">
              <option value="No" selected>No</option>
              <option value="Si">Si</option>
            </select>
          </div>
          <div class="col-md-7 ant-cual-container" id="ant_<?= $key_lower ?>_cual_container" style="display:none;">
            <?php if (isset($opciones_antecedentes[$key])):
                 if(isset($opciones_antecedentes[$key])):
             ?>
              <select class="form-select" name="ant_<?= $key_lower ?>_cual[]" multiple>
                <?php foreach ($opciones_antecedentes[$key] as $opcion): ?>
                  <option value="<?= htmlspecialchars($opcion ?? '') ?>"><?= htmlspecialchars($opcion ?? '') ?></option>
                <?php endforeach; ?>
              </select>
              <input type="text" class="form-control mt-2 ant-otro-input" id="ant_<?= $key_lower ?>_cual_otro" name="ant_<?= $key_lower ?>_cual_otro" placeholder="Especifique 'Otro'" style="display:none;">
              <small class="form-text text-muted">Puedes seleccionar varias (Ctrl/Cmd + clic).</small>
            <?php else: ?>
              <input type="text" class="form-control" name="ant_<?= $key_lower ?>_cual" placeholder="¿Cuál(es)?">
            <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php } ?>
    </div>
</section>