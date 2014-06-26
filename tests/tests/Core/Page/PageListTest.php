<?php

/**
 * Created by PhpStorm.
 * User: andrew
 * Date: 6/10/14
 * Time: 7:47 AM
 */

class PageListTest extends \PageTestCase {

    /** @var \Concrete\Core\Page\PageList */
    protected $list;

    protected $pageData = array(
        array(
            'Test Page 1', false,
        ),
        array(
            'Abracadabra', false,
        ),
        array(
            'Brace Yourself', false, 'alternate'
        ),
        array(
            'Foobler', '/test-page-1',
        ),
        array(
            'Test Page 2', false
        ),
        array(
            'Holy Mackerel', false
        ),
        array(
            'Another Fun Page', false, 'alternate'
        ),
        array(
            'Foo Bar', '/test-page-2'
        ),
        array(
            'Test Page 3', false
        ),
        array(
            'Another Page', false, 'alternate', 'right_sidebar'
        ),
        array(
            'More Testing', false, 'alternate'
        ),
        array(
            'Foobler', '/another-fun-page', 'another'
        )
    );

    public function setUp()
    {
        $this->tables = array_merge($this->tables, array(
            'PermissionAccessList',
            'PageTypeComposerFormLayoutSets',
            'AttributeSetKeys',
            'AttributeSets',
            'AttributeKeyCategories',
            'PermissionAccessEntityTypes',
            'Packages',
            'AttributeKeys',
            'AttributeTypes'

        ));

        parent::setUp();
        \Concrete\Core\Permission\Access\Entity\Type::add('page_owner', 'Page Owner');
        \Concrete\Core\Permission\Category::add('page');
        \Concrete\Core\Permission\Key\Key::add('page', 'view_page', 'View Page', '', 0, 0);
        PageTemplate::add('left_sidebar', 'Left Sidebar');
        PageTemplate::add('right_sidebar', 'Right Sidebar');
        PageType::add(array(
            'handle' => 'alternate',
            'name' => 'Alternate'
        ));
        PageType::add(array(
            'handle' => 'another',
            'name' => 'Another'
        ));

        foreach($this->pageData as $data) {
            $c = call_user_func_array(array($this, 'createPage'), $data);
            $c->reindex();
        }

        $this->list = new \Concrete\Core\Page\PageList();
        $this->list->ignorePermissions();
    }

    protected function addAlias()
    {
        $subject = Page::getByPath('/test-page-2');
        $parent = Page::getByPath('/another-fun-page');
        $subject->addCollectionAlias($parent);
    }

    public function testGetUnfilteredTotal()
    {
        $this->assertEquals(13, $this->list->getTotalResults());
    }

    public function testFilterByTypeNone()
    {
        $this->list->filterByPageTypeHandle('fuzzy');
        $this->assertEquals(0, $this->list->getTotalResults());
    }

    public function testFilterByTypeValid1()
    {
        $this->list->filterByPageTypeHandle('basic');
        $this->assertEquals(7, $this->list->getTotalResults());

        $pagination = $this->list->getPagination();
        $this->assertEquals(7, $pagination->getTotalResults());
        $results = $pagination->getCurrentPageResults();
        $this->assertEquals(7, count($results));
        $this->assertInstanceOf('\Concrete\Core\Page\Page', $results[0]);
    }

    public function testFilterByTypeValid2()
    {
        $this->list->filterByPageTypeHandle(array('alternate', 'another'));
        $this->assertEquals(5, $this->list->getTotalResults());
    }

    public function testSortByIDAscending()
    {
        $this->list->sortByCollectionIDAscending();
        $pagination = $this->list->getPagination();
        $results = $pagination->getCurrentPageResults();
        $this->assertEquals(1, $results[0]->getCollectionID());
        $this->assertEquals(2, $results[1]->getCollectionID());
        $this->assertEquals(3, $results[2]->getCollectionID());
    }

    public function testSortByNameAscending()
    {
        $this->list->sortByName();
        $pagination = $this->list->getPagination();
        $results = $pagination->getCurrentPageResults();
        $this->assertEquals('Abracadabra', $results[0]->getCollectionName());
        $this->assertEquals('Another Fun Page', $results[1]->getCollectionName());
        $this->assertEquals('Another Page', $results[2]->getCollectionName());
        $this->assertEquals('Brace Yourself', $results[3]->getCollectionName());
    }

    public function testFilterByKeywords()
    {
        $this->list->filterByKeywords('brac', true);
        $total = $this->list->getTotalResults();
        $this->assertEquals(2, $total);
    }

    public function testItemsPerPage()
    {
        $pagination = $this->list->getPagination();
        $pagination->setMaxPerPage(2);
        $pages = $pagination->getCurrentPageResults();
        $this->assertEquals(2, count($pages));
    }

    public function testPaginationObject()
    {
        $this->list->sortByCollectionIDAscending();
        $pagination = $this->list->getPagination();
        $pagination->setMaxPerPage(2);
        $this->assertInstanceOf('\Concrete\Core\Search\Pagination\Pagination', $pagination);
        $this->assertEquals(2, $pagination->getMaxPerPage());
        $this->assertEquals(13, $pagination->getTotalResults());
        $this->assertEquals(1, $pagination->getCurrentPage());
        $this->assertEquals(false, $pagination->hasPreviousPage());
        $this->assertEquals(true, $pagination->hasNextPage());
        $this->assertEquals(true, $pagination->haveToPaginate());
    }

    public function testAliasingAndBasicGet()
    {
        $this->addAlias();
        $this->list->sortBy('cID', 'desc');

        $results = $this->list->getResults();
        $this->assertEquals(14, count($results));
        $this->assertEquals('Test Page 2', $results[0]->getCollectionName());
        $this->assertEquals(true, $results[0]->isAlias());
    }

    public function testFilterByParentID()
    {
        $this->addAlias();
        $parent = Page::getByPath('/another-fun-page');
        $this->list->filterByParentID($parent->getCollectionID());
        $pagination = $this->list->getPagination();
        $results = $pagination->getCurrentPageResults();
        $this->assertEquals(2, count($results));
        $this->assertEquals(2, $pagination->getTotalResults());
    }

    public function testFilterByActiveAndSystem()
    {

        \SinglePage::add(TRASH_PAGE_PATH);

        $c = Page::getByPath('/test-page-2');
        $c->moveToTrash();

        $results = $this->list->getResults();
        $this->assertEquals(11, count($results));

        $this->list->includeSystemPages(); // This includes the items inside trash because we're stupid.
        $totalResults = $this->list->getTotalResults();
        $this->assertEquals(12, $totalResults);
        $pagination = $this->list->getPagination();
        $this->assertEquals(12, $pagination->getTotalResults());
        $results = $this->list->getResults();
        $this->assertEquals(12, count($results));

        $this->list->includeInactivePages();
        $totalResults = $this->list->getTotalResults();
        $this->assertEquals(14, $totalResults);
        $pagination = $this->list->getPagination();
        $this->assertEquals(14, $pagination->getTotalResults());
        $results = $this->list->getResults();
        $this->assertEquals(14, count($results));
    }


}
 