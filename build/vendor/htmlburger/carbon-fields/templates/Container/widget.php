<div class="carbon-container">
	<fieldset class="container-<?php 
namespace FuerteWpDep;

echo $this->get_id();
?>" data-json="<?php 
echo \urlencode(\json_encode($this->to_json(\false)));
?>"></fieldset>
	<?php 
if (!$this->has_fields()) {
    ?>
		<?php 
    _e('No options are available for this widget.', 'carbon-fields');
    ?>
	<?php 
}
?>
</div>
<?php 
