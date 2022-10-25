<?php
namespace Irbis\RecordSet;

use Irbis\RecordSet;


/**
 * Clase abstracta que heredarán los miembros del backbone
 *
 * @package 	irbis/recordset
 * @author		Jorge Luis Quico C. <jorge.quico@cavia.io>
 * @version		2.0
 */
abstract class Member {
	protected $recordset;
	protected $record;

	public function setRecordSet (RecordSet $recordset = null) {
		$this->recordset = $recordset;
		return $this;
	}

	public function setRecord (Record $record = null) {
		$this->record = $record;
		return $this;
	}
}