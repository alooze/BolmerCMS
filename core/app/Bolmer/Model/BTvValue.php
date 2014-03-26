<?php namespace Bolmer\Model;

use Granada\Orm\Wrapper as ORMWrapper;

class BTvValue extends \Bolmer\Model
{
    public static $_table = 'site_tmplvar_contentvalues';

    public function doc() {
        return $this->belongs_to('\Bolmer\Model\BDoc', 'contentid');
    }

    /**
     * @TODO: связь с ТВ
     */
}