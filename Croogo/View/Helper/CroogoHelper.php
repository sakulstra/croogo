<?php

App::uses('AppHelper', 'View/Helper');
App::uses('CroogoStatus', 'Croogo.Lib');

/**
 * Croogo Helper
 *
 * @category Helper
 * @package  Croogo.Croogo.View.Helper
 * @version  1.0
 * @author   Fahad Ibnay Heylaal <contact@fahad19.com>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.croogo.org
 */
class CroogoHelper extends AppHelper {

	public $helpers = array(
		'Form' => array('className' => 'Croogo.CroogoForm'),
		'Html' => array('className' => 'Croogo.CroogoHtml'),
		'Croogo.Layout',
		'Menus.Menus',
	);

/**
 * Provides backward compatibility for deprecated methods
 */
	public function __call($method, $params) {
		if ($method == 'settingsInput') {
			if (!$this->_View->Helpers->loaded('SettingsForm')) {
				$this->_View->Helpers->load('Settings.SettingsForm');
			}
			$callable = array($this->_View->SettingsForm, 'input');
			return call_user_func_array($callable, $params);
		}
	}

/**
 * Default Constructor
 *
 * @param View $View The View this helper is being attached to.
 * @param array $settings Configuration settings for the helper.
 */
	public function __construct(View $View, $settings = array()) {
		$this->helpers[] = Configure::read('Site.acl_plugin') . '.' . Configure::read('Site.acl_plugin');
		parent::__construct($View, $settings);
		$this->_CroogoStatus = new CroogoStatus();
	}

/**
 * Before Render callback
 */
	public function beforeRender($viewFile) {
		if (isset($this->request->params['admin'])) {
			Croogo::dispatchEvent('Croogo.setupAdminData', $this->_View);
		}
	}

	public function statuses() {
		return $this->_CroogoStatus->statuses();
	}

/**
 * Convenience method to Html::script() for admin views
 *
 * This method does nothing if request is ajax or not in admin prefix.
 *
 * @see HtmlHelper::script()
 * @param $url string|array Javascript files to include
 * @param array|boolean Options or Html attributes
 * @return mixed String of <script /> tags or null
 */
	public function adminScript($url, $options = array()) {
		$options = Hash::merge(array('inline' => false), $options);
		if ($this->request->is('ajax') || empty($this->request->params['admin'])) {
			return;
		}
		return $this->Html->script($url, $options);
	}

/** Generate Admin menus added by CroogoNav::add()
 *
 * @param array $menus
 * @param array $options
 * @return string menu html tags
 */
	public function adminMenus($menus, $options = array(), $parent = null) {
		$options = Hash::merge(array(
			'hasChild' => array(
				'class' => 'hasChild'
			),
			'data-target' => false,
			'icon' => array(
				'class' => 'icon-'
			),
			'root' => array('ul' => array(), 'li' => array(), 'a' => array()),
			'node' => array('ul' => array(), 'li' => array(), 'a' => array()),
			'leaf' => array('ul' => array(), 'li' => array(), 'a' => array()),
		), $options);

		$aclPlugin = Configure::read('Site.acl_plugin');
		$userId = AuthComponent::user('id');
		if (empty($userId)) {
			return '';
		}

		$sortedMenu = Hash::sort($menus, '{s}.weight', 'ASC');
		if (empty($this->Role)) {
			$this->Role = ClassRegistry::init('Users.Role');
			$this->Role->Behaviors->attach('Croogo.Aliasable');
		}
		$currentRole = $this->Role->byId($this->Layout->getRoleId());
		$result = null;
		foreach ($sortedMenu as $key => $menuEntry) {
			if (isset($menu['separator'])) {
				$liOptions['class'] = 'divider';
				$result .= $this->Html->tag('li', null, $liOptions);
				continue;
			}
			if ($currentRole != 'admin' && !$this->{$aclPlugin}->linkIsAllowedByUserId($userId, $menuEntry['url'])) {
				continue;
			}
			if ($parent == null) {
				$type = 'root';
			} else if (!empty($menuEntry['children'])) {
				$type = 'node';
			} else {
				$type = 'leaf';
			}
			$inA = null;
			if (is_array($menuEntry['icon'])) {
				$inA .= $this->Html->tag('i', '', array('class' => $options['icon']['class'] . $menuEntry['icon'][0])) . ' ';
			}else{
				$inA .= $this->Html->tag('i', '', array('class' => $options['icon']['class'] . $menuEntry['icon'])) . ' ';
			}
			$inA .= $menuEntry['title'];
			$menuEntry['url'] = $this->url($menuEntry['url']);
			$submenu = null;
			$aOptions = Hash::merge($options[$type]['a'], array('href' => $menuEntry['url']));
			if($options['data-target']){
				$aOptions['data-target'] = '#menu-' . $key;
			}
			if ($menuEntry['url'] == env('REQUEST_URI')) {
				if (isset($aOptions['class'])) {
					$aOptions['class'] .= ' current';
				} else {
					$aOptions['class'] = 'current';
				}
			}
			if (!empty($menuEntry['children'])) {
				if(isset($options['hasChild']['class'])){
					if(!isset($aOptions['class'])){
						$aOptions['class'] = $options['hasChild']['class'];
					}else{
						$aOptions['class'] .= ' '.$options['hasChild']['class'];
					}
				}
				$submenu = $this->adminMenus($menuEntry['children'], $options, $key);
			}

			$inLi = $this->Html->tag('a', $inA, $aOptions);
			$result .= $this->Html->tag('li', $inLi . $submenu, $options[$type]['li']);
		}
		$ulOptions = array();
		if ($parent != null) {
			$ulOptions['id'] = 'menu-' . $parent;
		}
		$ulOptions = Hash::merge($options[$type]['ul'],$ulOptions);
		return $this->Html->tag('ul', $result, $ulOptions);
	}

/**
 * Show links under Actions column
 *
 * @param integer $id
 * @param array $options
 * @return string
 */
	public function adminRowActions($id, $options = array()) {
		$output = '';
		$rowActions = Configure::read('Admin.rowActions.' . Inflector::camelize($this->request->params['controller']) . '/' . $this->request->params['action']);
		if (is_array($rowActions)) {
			foreach ($rowActions as $title => $link) {
				$linkOptions = $options;
				$confirmMessage = false;
				if (is_array($link)) {
					$config = $link[key($link)];
					if (isset($config['options'])) {
						$linkOptions = Hash::merge($options, $config['options']);
					}
					if (isset($config['confirmMessage'])) {
						$confirmMessage = $config['confirmMessage'];
						unset($config['confirmMessage']);
					}
					if (isset($config['title'])) {
						$title = $config['title'];
					}
					$link = key($link);
				}
				$link = $this->Menus->linkStringToArray(str_replace(':id', $id, $link));
				$output .= $this->adminRowAction($title, $link, $linkOptions, $confirmMessage);
			}
		}
		return $output;
	}

/**
 * Show link under Actions column
 *
 * ### Options:
 *
 * - `method` - when 'POST' is specified, the FormHelper::postLink() will be
 *              used instead of HtmlHelper::link()
 * - `rowAction` when bulk submissions is used, defines which action to use.
 *
 * @param string $title The content to be wrapped by <a> tags.
 * @param string|array $url Cake-relative URL or array of URL parameters, or external URL (starts with http://)
 * @param array $options Array of HTML attributes.
 * @param string $confirmMessage JavaScript confirmation message.
 * @return string An `<a />` element
 */
	public function adminRowAction($title, $url = null, $options = array(), $confirmMessage = false) {
		$action = false;
		$options = Hash::merge(array(
			'escapeTitle' => false,
			'escape' => true,
		), $options);
		if (is_array($url)) {
			$action = $url['action'];
			if (isset($options['class'])) {
				$options['class'] .= ' ' . $url['action'];
			} else {
				$options['class'] = $url['action'];
			}
		}
		if (isset($options['icon']) && empty($title)) {
			$options['iconInline'] = false;
		}

		if (!empty($options['rowAction'])) {
			$options['data-row-action'] = $options['rowAction'];
			unset($options['rowAction']);
			return $this->_bulkRowAction($title, $url, $options, $confirmMessage);
		}

		if (!empty($options['method']) && strcasecmp($options['method'], 'post') == 0) {
			$usePost = true;
			unset($options['method']);
		}

		if ($action == 'delete' || isset($usePost)) {
			return $this->Form->postLink($title, $url, $options, $confirmMessage);
		}

		return $this->Html->link($title, $url, $options, $confirmMessage);
	}

/**
 * Creates a special type of link for use in admin area.
 *
 * Clicking the link will automatically check a corresponding checkbox
 * where element id is equal to $url parameter and immediately submit the form
 * it's on.  This works in tandem with Admin.processLink() in javascript.
 */
	protected function _bulkRowAction($title, $url = null, $options = array(), $confirmMessage = false) {
		if (!empty($confirmMessage)) {
			$options['data-confirm-message'] = $confirmMessage;
		}
		if (isset($options['icon'])) {
			$options['iconInline'] = false;
		}
		$output = $this->Html->link($title, $url, $options);
		return $output;
	}

/**
 * Create an action button
 */
	public function adminAction($title, $url, $options = array()) {
		$options = Hash::merge(array(
			'button' => 'default',
			'method' => 'get',
		), $options);
		if (strcasecmp($options['method'], 'post') == 0) {
			return $this->Html->tag('li',
				$this->Form->postLink($title, $url, $options)
			);
		}
		return $this->Html->tag('li',
			$this->Html->link($title, $url, $options)
		);
	}

/**
 * Create a tab title/link
 */
	public function adminTab($title, $url, $options = array()) {
		return $this->Html->tag('li',
			$this->Html->link($title, $url, Hash::merge(array(
				'data-toggle' => 'tab',
				), $options)
			)
		);
	}

/**
 * Show tabs
 *
 * @return string
 */
	public function adminTabs($show = null) {
		if (!isset($this->adminTabs)) {
			$this->adminTabs = false;
		}

		$output = '';
		$tabs = Configure::read('Admin.tabs.' . Inflector::camelize($this->request->params['controller']) . '/' . $this->request->params['action']);
		if (is_array($tabs)) {
			foreach ($tabs as $title => $tab) {
				$tab = Hash::merge(array(
					'options' => array(
						'linkOptions' => array(),
						'elementData' => array(),
						'elementOptions' => array(),
					),
				), $tab);

				if (!isset($tab['options']['type']) || (isset($tab['options']['type']) && (in_array($this->_View->viewVars['typeAlias'], $tab['options']['type'])))) {
					$domId = strtolower(Inflector::singularize($this->request->params['controller'])) . '-' . strtolower(Inflector::slug($title, '-'));
					if ($this->adminTabs) {
						list($plugin, $element) = pluginSplit($tab['element']);
						$elementOptions = Hash::merge(array(
							'plugin' => $plugin,
						), $tab['options']['elementOptions']);
						$output .= '<div id="' . $domId . '" class="tab-pane">';
						$output .= $this->_View->element($element, $tab['options']['elementData'], $elementOptions);
						$output .= '</div>';
					} else {
						$output .= $this->adminTab(__d('croogo', $title), '#' . $domId, $tab['options']['linkOptions']);
					}
				}
			}
		}

		$this->adminTabs = true;
		return $output;
	}

/**
 * Show Boxes
 *
 * @param array $boxNames
 */
	public function adminBoxes($boxName = null) {
		if (!isset($this->boxAlreadyPrinted)) {
			$this->boxAlreadyPrinted = array();
		}

		$output = '';
		$allBoxes = Configure::read('Admin.boxes.' . Inflector::camelize($this->request->params['controller']) . '/' . $this->request->params['action']);
		$allBoxes = empty($allBoxes) ? array() : $allBoxes;
		$boxNames = array();

		if (is_null($boxName)) {
			foreach ($allBoxes as $boxName => $value) {
				if (!in_array($boxName, $this->boxAlreadyPrinted)) {
					$boxNames[$boxName] = $allBoxes[$boxName];
				}
			}
		} elseif (!in_array($boxName, $this->boxAlreadyPrinted)) {
			if (array_key_exists($boxName, $allBoxes)) {
				$boxNames[$boxName] = $allBoxes[$boxName];
			}
		}

		foreach ($boxNames as $title => $box) {
			$issetType = isset($box['options']['type']);
			$typeInTypeAlias = $issetType && in_array($this->_View->viewVars['typeAlias'], $box['options']['type']);
			if (!$issetType || $typeInTypeAlias) {
				list($plugin, $element) = pluginSplit($box['element']);
				$output .= $this->Html->beginBox($title);
				$output .= $this->_View->element(
					$element,
					array(),
					array('plugin' => $plugin)
				);
				$output .= $this->Html->endBox();
				$this->boxAlreadyPrinted[] = $title;
			}
		}

		return $output;
	}

}
