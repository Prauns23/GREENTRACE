<?php
if (!isset($canManageSpecies, $species) || !$canManageSpecies) {
    return;
}
?>
<dialog class="species-dialog" id="species-editor" aria-labelledby="species-editor-title">
    <form id="species-form" enctype="multipart/form-data">
        <header class="species-dialog-header"><h2 id="species-editor-title">Add Tree Species</h2><p>Add another tree species in the list for our community to learn from!</p></header>
        <div class="species-form-body">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="add"><input type="hidden" name="id" value="">
            <label class="species-upload" id="species-upload" for="species-image">
                <span class="species-image-preview"><img id="species-image-preview" alt="Selected species image preview" hidden><i class="fas fa-cloud-upload-alt" id="species-upload-icon" aria-hidden="true"></i></span>
                <span>Click to upload the image or drag and drop<br><small>PNG, JPG up to 10 MB · optional</small></span>
                <input type="file" name="image" id="species-image" accept="image/jpeg,image/png">
            </label>
            <label for="species-name">Tree species name <span>*</span></label><input id="species-name" name="name" maxlength="100" placeholder="E.g., Fire Tree" required>
            <div class="species-form-row"><div><label for="species-scientific">Scientific Name <span>*</span></label><input id="species-scientific" name="scientific_name" maxlength="150" placeholder="E.g., Delonix regia" required></div><div><label for="species-category">Category <span>*</span></label><select id="species-category" name="category" required><option value="">Select Here</option><option value="native">Native</option><option value="introduced">Introduced</option></select></div></div>
            <label for="species-description">Description <span>*</span></label><textarea id="species-description" name="description" rows="4" maxlength="10000" placeholder="Enter a short description …" required></textarea>
            <label for="species-importance">Importance <span>*</span></label><textarea id="species-importance" name="importance" rows="3" maxlength="10000" placeholder="E.g., Provides shade and wildlife habitat (one benefit per line)" required></textarea>
            <label for="species-fact">Add fun fact</label><textarea id="species-fact" name="fun_fact" rows="2" maxlength="2000" placeholder="Add an interesting fact about this tree"></textarea>
            <p class="species-form-error" id="species-form-error" role="alert"></p>
        </div>
        <footer class="species-dialog-footer"><button type="button" data-close-species>Cancel</button><button type="submit" class="species-save" id="species-save">Add</button></footer>
    </form>
</dialog>
<dialog class="species-dialog species-confirm" id="species-confirm" aria-labelledby="species-confirm-title">
    <form id="species-confirm-form">
        <div class="species-form-body"><h2 id="species-confirm-title">Archive species?</h2><p id="species-confirm-copy"></p><p class="species-form-error" id="species-confirm-error" role="alert"></p></div>
        <footer class="species-dialog-footer"><button type="button" data-close-species>Cancel</button><button class="species-save" id="species-confirm-submit" type="submit">Archive</button></footer>
    </form>
</dialog>
<script id="species-edit-data" type="application/json"><?= json_encode($species, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
