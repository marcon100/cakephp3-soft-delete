<?php
namespace SoftDelete\Test\TestCase\Model\Table;

use Cake\TestSuite\TestCase;
use Cake\ORM\TableRegistry;
use Cake\Network\Exception\InternalErrorException;

/**
 * App\Model\Behavior\SoftDeleteBehavior Test Case
 */
class SoftDeleteBehaviorTest extends TestCase
{

    /**
     * fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.SoftDelete.users',
        'plugin.SoftDelete.blog_posts',
        'plugin.SoftDelete.tags'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->usersTable = TableRegistry::get('Users', ['className' => 'SoftDelete\Test\Fixture\UsersTable']);
        $this->postsTable = TableRegistry::get('BlogPosts', ['className' => 'SoftDelete\Test\Fixture\BlogPostsTable']);
        $this->tagsTable = TableRegistry::get('Tags', ['className' => 'SoftDelete\Test\Fixture\TagsTable']);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->usersTable);
        unset($this->postsTable);
        parent::tearDown();
    }

    /**
     * Tests that a soft deleted entities is not found when calling Table::find()
     */
    public function testFind()
    {
        $user = $this->usersTable->get(1);
        $user->deleted = date('Y-m-d H:i:s');
        $this->usersTable->save($user);

        $user = $this->usersTable->find()->where(['id' => 1])->first();
        $this->assertEquals(null, $user);

    }

    /**
     * Tests that a soft deleted entities is not found when calling Table::findByXXX()
     */
    public function testDynamicFinder()
    {
        $user = $this->usersTable->get(1);
        $user->deleted = date('Y-m-d H:i:s');
        $this->usersTable->save($user);

        $user = $this->usersTable->findById(1)->first();
        $this->assertEquals(null, $user);
    }

    public function testFindWithOrWhere()
    {
        $user = $this->usersTable->get(2);
        $this->usersTable->delete($user);

        $query = $this->usersTable->find()->where(['id' => 1])->orWhere(['id' => 2]);
        $this->assertEquals(1, $query->count());
    }

    /**
     * Tests that Table::deleteAll() does not hard delete
     */
    public function testDeleteAll()
    {
        $this->usersTable->deleteAll([]);
        $this->assertEquals(0, $this->usersTable->find()->count());
        $this->assertNotEquals(0, $this->usersTable->find('all', ['withDeleted'])->count());

        $this->postsTable->deleteAll([]);
        $this->assertEquals(0, $this->postsTable->find()->count());
        $this->assertNotEquals(0, $this->postsTable->find('all', ['withDeleted'])->count());

    }

    /**
     * Tests that Table::delete() does not hard delete.
     */
    public function testDelete()
    {
        $user = $this->usersTable->get(1);
        $this->usersTable->delete($user);
        $user = $this->usersTable->findById(1)->first();
        $this->assertEquals(null, $user);

        $user = $this->usersTable->find('all', ['withDeleted'])->where(['id' => 1])->first();
        $this->assertNotEquals(null, $user);
        $this->assertNotEquals(null, $user->deleted);
    }

    /**
     * Tests that soft deleting an entity also soft deletes its belonging entities.
     */
    public function testHasManyAssociation()
    {
        $user = $this->usersTable->get(1);
        $this->usersTable->delete($user);

        $count = $this->postsTable->find()->where(['user_id' => 1])->count();
        $this->assertEquals(0, $count);

        $count = $this->postsTable->find('all', ['withDeleted'])->where(['user_id' => 1])->count();
        $this->assertEquals(2, $count);
    }

    /**
     * Tests that soft deleting affects counters the same way that hard deleting.
     */
    public function testCounterCache()
    {
        $post = $this->postsTable->get(1);
        $this->postsTable->delete($post);
        $this->assertNotEquals(null, $this->postsTable->find('all', ['withDeleted'])->where(['id' => 1])->first());
        $this->assertEquals(null, $this->postsTable->findById(1)->first());

        $user = $this->usersTable->get(1);
        $this->assertEquals(1, $user->posts_count);
    }

    public function testHardDelete()
    {
        $user = $this->usersTable->get(1);
        $this->usersTable->hardDelete($user);
        $user = $this->usersTable->findById(1)->first();
        $this->assertEquals(null, $user);

        $user = $this->usersTable->find('all', ['withDeleted'])->where(['id' => 1])->first();
        $this->assertEquals(null, $user);
    }

    /**
     * Tests hardDeleteAll.
     */
    public function testHardDeleteAll()
    {
        $affectedRows = $this->postsTable->hardDeleteAll(new \DateTime('now'));
        $this->assertEquals(0, $affectedRows);

        $postsRowsCount = $this->postsTable->find('all', ['withDeleted'])->count();

        $this->postsTable->delete($this->postsTable->get(1));
        $affectedRows = $this->postsTable->hardDeleteAll(new \DateTime('now'));
        $this->assertEquals(1, $affectedRows);

        $newpostsRowsCount = $this->postsTable->find('all', ['withDeleted'])->count();
        $this->assertEquals($postsRowsCount - 1, $newpostsRowsCount);
    }

    /**
     * Using a table with a custom soft delete field, ensure we can still filter
     * the found results properly.
     *
     * @return void
     */
    public function testFindingWithCustomField()
    {
        $query = $this->tagsTable->find();
        $this->assertEquals(1, $query->count());

        $query = $this->tagsTable->find('all', ['withDeleted' => true]);
        $this->assertEquals(3, $query->count());
    }

    /**
     * Ensure that when deleting a record which has a custom field defined in
     * the table, that it is still soft deleted.
     *
     * @return void
     */
    public function testDeleteWithCustomField()
    {
        $tag = $this->tagsTable->get(1);
        $this->tagsTable->delete($tag);

        $query = $this->tagsTable->find();
        $this->assertEquals(0, $query->count());
    }

    /**
     * With a custom soft delete field ensure that a soft deleted record can
     * still be permanently removed.
     *
     * @return void
     */
    public function testHardDeleteWithCustomField()
    {
        $tag = $this->tagsTable->find('all', ['withDeleted'])
            ->where(['id' => 2])
            ->first();

        $this->tagsTable->hardDelete($tag);

        $tag = $this->tagsTable->find('all', ['withDeleted'])
            ->where(['id' => 2])
            ->first();

        $this->assertEquals(null, $tag);
    }
}
