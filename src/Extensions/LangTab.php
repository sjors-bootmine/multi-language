<?php

namespace OpenAdmin\MultiLanguage\Extensions;

use Illuminate\Database\Eloquent\Relations\HasMany as Relation;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenAdmin\Admin\Form\Field\HasMany;
use OpenAdmin\Admin\Form\NestedForm;

/**
 * Class LangTabAll.
 */
class LangTab extends HasMany
{
    /**
     * Form builder.
     *
     * @var \Closure
     */
    protected $builder = null;

    /**
     * Locales.
     *
     * @var Array
     */
    protected $locales = null;

    /**
     * Form data.
     *
     * @var array
     */
    protected $value = [];

    /**
     * View Mode.
     *
     * Supports `default` and `tab` currently.
     *
     * @var string
     */
    protected $viewMode = 'tab';

    /**
     * Available views for HasMany field.
     *
     * @var array
     */
    protected $view = 'multi-language::langTab';

    public function __construct($column, $arguments = [], $relationPath = '')
    {
        $this->locales = config('translatable.locales');
        parent::__construct($column, $arguments, $relationPath);
    }

    public function setLocales(array $locales = [])
    {
        $this->locales = $locales;
        return $this;
    }

    /**
     * Get validator for this field.
     *
     * @param array $input
     *
     * @return bool|Validator
     */
    public function getValidator(array $_input)
    {
        if (!Arr::has($_input, $this->column)) {
            return false;
        }

        $input = Arr::get($_input, $this->column);
        Arr::set($input_only, $this->column, $input);


        $form = $this->buildNestedForm($this->column, $this->builder);

        $rules = $attributes = [];

        /* @var Field $field */
        foreach ($form->fields() as $field) {
            if (!$fieldRules = $field->getRules()) {
                continue;
            }

            $column = $field->column();

            if (is_array($column)) {
                foreach ($column as $key => $name) {
                    $rules[$name . $key] = $fieldRules;
                }

                $this->resetInputKey($input_only, $column);
            } else {
                $rules[$column] = $fieldRules;
            }

            $attributes = array_merge(
                $attributes,
                $this->formatValidationAttribute($input_only, $field->label(), $column)
            );
        }

        Arr::forget($rules, NestedForm::REMOVE_FLAG_NAME);

        if (empty($rules)) {
            return false;
        }

        $newRules = [];
        $newInput = [];

        foreach ($rules as $column => $rule) {
            foreach (array_keys($input) as $key) {
                $newRules["{$this->column}.$key.$column"] = $rule;
                $a_key                                    = "{$this->column}.$key.$column";
                if (Arr::has($_input, $a_key) && //isset($input[$this->column][$key][$column]) &&
                    is_array(Arr::get($_input, $a_key))) {
                    foreach (Arr::get($_input, $a_key) as $vkey => $value) {
                        $newInput["{$a_key}.$vkey"] = $value;
                    }
                }
            }
        }

        if (empty($newInput)) {
            $newInput = $input_only;
        }

        return \validator($newInput, $newRules, $this->getValidationMessages(), $attributes);
    }

    /**
     * Build a Nested form.
     *
     * @param string   $column
     * @param \Closure $builder
     * @param null     $model
     *
     * @return NestedForm
     */

    protected function buildNestedForm($column, \Closure $builder, $model = null, $langKey = null)
    {
        $form = new NestedForm($column, $model, $this->relationPath);
        if ($langKey) {
            $form->setKey($langKey);
        }
        
        $form->setForm($this->form);
 
        call_user_func($builder, $form);
 
        $form->hidden($this->getKeyName());
        $form->hidden('locale');
 
        $form->hidden(NestedForm::REMOVE_FLAG_NAME)->default(0)->addElementClass(NestedForm::REMOVE_FLAG_CLASS);
 
        return $form;
    }
           

    /**
     * Build Nested form for related data.
     *
     * @throws \Exception
     *
     * @return array
     */
    protected function buildRelatedForms()
    {
        if (is_null($this->form)) {
            return [];
        }

        if (Str::contains($this->relationPath, '.')) {
            $relationModelName = explode('.', $this->relationPath)[0];
            $model             = $this->form->model()->{$relationModelName}()->getRelated();
            $relationName      = 'translations';
        } else {
            $relationName = $this->relationName;
            $model        = $this->form->model();
        }


        $relation = call_user_func([$model, $relationName]);

        if (!$relation instanceof Relation && !$relation instanceof MorphMany) {
            throw new \Exception('hasMany field must be a HasMany or MorphMany relation.');
        }

        $forms = [];

        /*
         * If redirect from `exception` or `validation error` page.
         *
         * Then get form data from session flash.
         *
         * Else get data from database.
         */
        if ($values = old($this->column)) {
            foreach ($values as $data) {
                $key = Arr::get($data, 'locale');

                $model = $relation->getRelated()->replicate()->forceFill($data);

                $forms[$key] = $this->buildNestedForm($this->column, $this->builder, $model, $key)
                    ->fill($data);
            }
        } else {
            if (empty($this->value)) {
                return [];
            }
            $values = $this->checkLocals($this->value, $model);

            foreach ($values as $data) {
                $key = Arr::get($data, 'locale');

                $model = $relation->getRelated()->replicate()->forceFill($data);
                
                $forms[$key] = $this->buildNestedForm($this->column, $this->builder, $model, $key)
                    ->fill($data);
            }
        }

        return $forms;
    }

    public function checkLocals($values)
    {
        $emptyForm  = $this->buildNestedForm($this->column, $this->builder);
        $foreignKey = $emptyForm->getForeignKey();

        $locales = Arr::flatten(config('translatable.locales'));
        $found   = [];
        foreach ($values as $row) {
            $found[] = $row['locale'];
        }
        $diff = array_diff($locales, $found);
        foreach ($diff as $missing) {
            //=> $this->parentId
            $values[] = ['locale' => $missing,$foreignKey => $this->parentId];
        }

        return $values;
    }
   

    protected function setupScriptForDefaultView($templateScript)
    {
        // do nothing
        // no delete & add field present
    }
 
    /**
     * Builder the `HasMany` field.
     *
     * @throws \Exception
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        $builder = $this->buildNestedForm($this->column, $this->builder);

        $has_parent = true;
        if (Str::contains($this->relationPath, '.')) {
            if (!$this->parentId) {
                $has_parent = false;
            }
        }

        $template_fields         = [];
        list($template, $script) = $builder->getTemplateHtmlAndScript();

        $f = $builder->fields();
        foreach ($f as $field) {
            $template_fields[] = $field;
        }

        $this->setupScript($script);
        
        return parent::fieldRender([
            'has_parent'      => $has_parent,
            'locales'         => Arr::flatten($this->locales),
            'parentSelector'  => $this->parentColumn ? $this->parentColumn . '-' . $this->parentId : '',
            'parentName'      => $this->parentColumn ? $this->parentColumn . '[' . $this->parentId . ']' : '',
            'columnName'      => $this->parentColumn ? '[' . $this->column . ']' : $this->column,
            'showAsField'     => $this->showAsFieldIsSet ? $this->showAsField : true,
            'uniqueId'        => $this->uniqueId,
            'column_var'      => $this->column_var,
            'column_class'    => $this->column_class,
            'forms'           => $this->buildRelatedForms(),
            'template'        => $template,
            'template_fields' => $template_fields,
            'relationName'    => $this->relationName,
            'verticalAlign'   => $this->verticalAlign,
            'options'         => $this->options,
        ]);
    }
}
