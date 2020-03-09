<?php

class ObjectModel extends ObjectModelCore{

	/* Override of PrestaShop ObjectModelCore's "validateFieldsLang" method  */
	/* I did this because of the part where it sets empty string for the default language and therefore it causes "validateField" to throw an exception. */
	/* I just couldn't find another way to make it work as needed. In PS 1.7.6 I solved this problem just by passing default lang id into ObjectModel's constructor. */
	/* But in PS 1.7.3(and probably the near versions too) it doesn't work. */
	public function validateFieldsLang($die = true, $errorReturn = false)
    {
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
        foreach ($this->def['fields'] as $field => $data) {
            if (empty($data['lang'])) {
                continue;
            }

            $values = $this->$field;

            // If the object has not been loaded in multilanguage, then the value is the one for the current language of the object
            if (!is_array($values)) {
                $values = array($this->id_lang => $values);
            }

            // OVERRIDE!!! The value for the default must always be set, so we take the value of first language in the $values array
            if (!isset($values[$defaultLang])) {
                $values[$defaultLang] = array_values($values)[0];
            }

            foreach ($values as $id_lang => $value) {
                if (is_array($this->update_fields) && empty($this->update_fields[$field][$id_lang])) {
                    continue;
                }

                $message = $this->validateField($field, $value, $id_lang);
                if ($message !== true) {
                    if ($die) {
                        throw new PrestaShopException($message);
                    }
                    return $errorReturn ? $message : false;
                }
            }
        }

        return true;
    }

}