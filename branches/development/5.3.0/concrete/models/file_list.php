<?

defined('C5_EXECUTE') or die(_("Access Denied."));

/**
*
* An object that allows a filtered list of files to be returned.
* @package Files
*
*/
class FileList extends DatabaseItemList {

	private $fileAttributeFilters = array();
	protected $autoSortColumns = array('fvFilename', 'fvAuthorName','fvTitle', 'fvDateAdded', 'fvSize');
	protected $itemsPerPage = 10;
	/* magic method for filtering by page attributes. */

	public function __call($nm, $a) {
		if (substr($nm, 0, 8) == 'filterBy') {
			$txt = Loader::helper('text');
			$attrib = $txt->uncamelcase(substr($nm, 8));
			if (count($a) == 2) {
				$this->filterByFileAttribute($attrib, $a[0], $a[1]);
			} else {
				$this->filterByFileAttribute($attrib, $a[0]);
			}
		}			
	}

	/** 
	 * Filters by type of collection (using the ID field)
	 * @param mixed $ctID
	 */
	public function filterByExtension($ext) {
		$this->filter('fv.fvExtension', $ext, '=');
	}

	/** 
	 * Filters by type of collection (using the ID field)
	 * @param mixed $ctID
	 */
	public function filterByType($type) {
		$this->filter('fv.fvType', $type, '=');
	}
	
	/** 
	 * Filters by "keywords" (which searches everything including filenames, title, tags, users who uploaded the file, tags)
	 */
	public function filterByKeywords($keywords) {
		$db = Loader::db();
		$keywordsExact = $db->quote($keywords);
		$keywords = $db->quote('%' . $keywords . '%');
		$this->filter(false, '(fvFilename like ' . $keywords . ' or fvTitle like ' . $keywords . ' or fvTags like ' . $keywords . ' or u.uName = ' . $keywordsExact . ')');
	}
	
	/** 
	 * Filters by files found in a certain set */
	public function filterBySet($fs) {
		$this->addToQuery("left join FileSetFiles fsf on fsf.fID = f.fID");
		$this->filter('fsf.fsID', $fs->getFileSetID(), '=');
	}
	/** 
	 * Filters the file list by file size (in kilobytes)
	 */
	public function filterBySize($from, $to) {
		$this->filter('fv.fvSize', $from * 1024, '>=');
		$this->filter('fv.fvSize', $to * 1024, '<=');
	}
	
	/** 
	 * Filters by public date
	 * @param string $date
	 */
	public function filterByDateAdded($date, $comparison = '=') {
		$this->filter('f.fDateAdded', $date, $comparison);
	}
	
	/** 
	 * Filters the list by collection attribute
	 * @param string $handle Collection Attribute Handle
	 * @param string $value
	 */
	public function filterByFileAttribute($handle, $value, $comparison = '=') {
		$this->fileAttributeFilters[] = array($handle, $value, $comparison);
	}
	
	/** 
	 * If true, pages will be checked for permissions prior to being returned
	 * @param bool $checkForPermissions
	 */
	public function displayOnlyPermittedPages($checkForPermissions) {
		$this->displayOnlyPermittedPages = $checkForPermissions;
	}
	
	protected function setBaseQuery() {
		$this->setQuery('SELECT f.fID, u.uName as fvAuthorName
		FROM Files f INNER JOIN FileVersions fv ON f.fID = fv.fID 
		LEFT JOIN Users u on u.uID = fv.fvAuthorUID
		');
	}
	
	protected function setupFileAttributeFilters() {
		$db = Loader::db();
		$i = 1;
		foreach($this->fileAttributeFilters as $caf) {
			$fakID = $db->GetOne("select fakID from FileAttributeKeys where akHandle = ?", array($caf[0]));
			$tbl = "fav_{$i}";
			$this->addToQuery("left join FileAttributeValues $tbl on {$tbl}.fID = fv.fID and fv.fvID = {$tbl}.fvID");
			$this->filter($tbl . '.value', $caf[1], $caf[2]);
			$this->filter($tbl . '.fakID', $fakID);
			$i++;
		}
	}
	
	/** 
	 * Returns an array of page objects based on current settings
	 */
	public function get($itemsToGet = 0, $offset = 0) {
		$files = array();
		Loader::model('file');
		$this->setBaseQuery();
		$this->filter('fvIsApproved', 1);
		$ipp = $this->itemsPerPage; // we store this in case a value is being used for paging that is separate from this value we want to use
		
		$this->setItemsPerPage(0); // no limit
		$this->setupFileAttributeFilters();
		$r = parent::get();
		foreach($r as $row) {
			$f = File::getByID($row['fID']);			
			$files[] = $f;
		}
		$this->setItemsPerPage($ipp);
		$this->start = $offset;
		return array_slice($files, $offset, $itemsToGet);
	}
	
	public static function getExtensionList() {
		$db = Loader::db();
		$col = $db->GetCol('select distinct(trim(fvExtension)) as extension from FileVersions where fvIsApproved = 1 and fvExtension <> ""');
		return $col;
	}

	public static function getTypeList() {
		$db = Loader::db();
		$col = $db->GetCol('select distinct(trim(fvType)) as type from FileVersions where fvIsApproved = 1 and fvType <> 0');
		return $col;
	}

}