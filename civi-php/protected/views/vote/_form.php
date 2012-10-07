<div class="form">

<?php $form=$this->beginWidget('CActiveForm', array(
	'id'=>'vote-form',
	'enableAjaxValidation'=>false,
)); ?>

	<?php echo Yii::t('app', 'forms.required'); ?>

	<?php echo $form->errorSummary($model); ?>

	<div class="row">
		<?php echo $form->labelEx($categoryModel,'name'); ?>
		<p><?php echo $categoryModel->name; ?></p>
	</div>

	<div class="row">
		<?php echo $form->labelEx($model,'candidate_id'); ?>
<?php

$this->widget('ext.combobox.EJuiComboBox', array(
	'name' => 'candidate',
	'data' => $candidates,
	'options' => array(
		'allowText' => false,
	),
	// Options passed to the text input
	'htmlOptions' => array('size' => 45),
));

?>
		<?php echo $form->error($model,'candidate_id'); ?>
	</div>

	<div class="row">
		<?php echo $form->labelEx($model,'reason'); ?>
		<?php echo $form->textField($model,'reason',array('size'=>60,'maxlength'=>128)); ?>
		<?php echo $form->error($model,'reason'); ?>
	</div>
	<?php echo $form->hiddenField($model,'category_id'); ?>

	<div class="row buttons">
		<?php echo CHtml::submitButton($model->isNewRecord ? Yii::t('app', 'vote.button') : Yii::t('app', 'vote.update.button')); ?>
	</div>

<?php $this->endWidget(); ?>

</div><!-- form -->