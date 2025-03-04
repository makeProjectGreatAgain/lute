<?php declare(strict_types=1);

require_once __DIR__ . '/../../db_helpers.php';
require_once __DIR__ . '/../../DatabaseTestBase.php';

use App\Entity\Term;
use App\Repository\TermTagRepository;

final class TermRepository_Test extends DatabaseTestBase
{

    public function childSetUp() {
        $this->load_languages();
    }
    
    public function test_save()
    {
        DbHelpers::assertRecordcountEquals("select * from words", 0, "no terms");
        $t = new Term($this->spanish, 'perro');
        $this->term_repo->save($t, true);
        DbHelpers::assertRecordcountEquals("select * from words", 1, "saved");
    }

    public function test_remove()
    {
        DbHelpers::assertRecordcountEquals("select * from words", 0, "no terms");
        $t = new Term($this->spanish, 'perro');
        $this->term_repo->save($t, true);
        DbHelpers::assertRecordcountEquals("select * from words", 1, "saved");
        $this->term_repo->remove($t, true);
        DbHelpers::assertRecordcountEquals("select * from words", 0, "no terms, removed");
    }

    public function test_flush()
    {
        DbHelpers::assertRecordcountEquals("select * from words", 0, "no terms");
        $t = new Term($this->spanish, 'perro');
        $this->term_repo->save($t, false);
        DbHelpers::assertRecordcountEquals("select * from words", 0, "not saved yet!");
        $this->term_repo->flush();
        DbHelpers::assertRecordcountEquals("select * from words", 1, "now saved");

    }

    public function test_create_and_save()
    {
        $t = new Term($this->spanish, 'HOLA');
        $t->setStatus(1);
        $t->setWordCount(1);
        $t->setTranslation('hi');
        $t->setRomanization('ho-la');
        $this->term_repo->save($t, true);

        $this->assertEquals($t->getTextLC(), 'hola', "sanity check of case");

        $sql = "select WoText, WoTextLC from words where WoID={$t->getID()}";
        $expected = [ "HOLA; hola" ];
        DbHelpers::assertTableContains($sql, $expected, "sanity check on save");
    }

    public function test_word_with_parent_and_tags()
    {
        $t = new Term($this->spanish, "HOLA");
        $p = new Term($this->spanish, "PARENT");
        $t->setParent($p);
        $t->addTermTag($this->termtag_repo->findOrCreateByText('tag'));
        $this->term_repo->save($t, true);

        $sql = "select WoID, WoText, WoTextLC from words";
        $expected = [ "1; HOLA; hola", "2; PARENT; parent" ];
        DbHelpers::assertTableContains($sql, $expected, "sanity check on save");

        DbHelpers::assertTableContains("select WpWoID, WpParentWoID from wordparents", [ '1; 2' ], 'wp');

        // Hacky sql check.
        $sql = "select w.WoText, p.WoText as ptext, tags.TgText 
            FROM words w
            INNER JOIN wordparents on WpWoID = w.WoID
            INNER JOIN words p on p.WoID = wordparents.WpParentWoID
            INNER JOIN wordtags on WtWoID = w.WoID
            INNER JOIN tags on TgID = WtTgID";
        $exp = [ "HOLA; PARENT; tag" ];
        DbHelpers::assertTableContains($sql, $exp, "parents, tags");
    }

    public function test_change_parent()
    {
        $t = new Term($this->spanish, "HOLA");
        $p = new Term($this->spanish, "PARENT");
        $t->setParent($p);
        $this->term_repo->save($t, true);

        // Hacky sql check.
        $sql = "select w.WoText, p.WoText as ptext
            FROM words w
            LEFT JOIN wordparents on WpWoID = w.WoID
            LEFT JOIN words p on p.WoID = wordparents.WpParentWoID
            WHERE w.WoID = {$t->getID()}";
        $exp = [ "HOLA; PARENT" ];
        DbHelpers::assertTableContains($sql, $exp, "parent");

        $o = new Term($this->spanish, "OTHER");
        $t->setParent($o);
        $this->term_repo->save($t, true);

        $exp = [ "HOLA; OTHER" ];
        DbHelpers::assertTableContains($sql, $exp, "parent changed");
    }

    public function test_remove_parent()
    {
        $t = new Term($this->spanish, "HOLA");
        $p = new Term($this->spanish, "PARENT");
        $t->setParent($p);
        $this->term_repo->save($t, true);

        // Hacky sql check.
        $sql = "select w.WoText, p.WoText as ptext
            FROM words w
            LEFT JOIN wordparents on WpWoID = w.WoID
            LEFT JOIN words p on p.WoID = wordparents.WpParentWoID
            WHERE w.WoID = {$t->getID()}";
        $exp = [ "HOLA; PARENT" ];
        DbHelpers::assertTableContains($sql, $exp, "parents");

        $t->setParent(null);
        $this->term_repo->save($t, true);
        $exp = [ "HOLA; NULL" ];
        DbHelpers::assertTableContains($sql, $exp, "parent removed, tags");
    }

    // Image saves:

    /**
     * @group images
     */
    public function test_save_with_image()
    {
        $t = new Term($this->spanish, "HOLA");
        $t->setCurrentImage('hello.png');

        $this->assertEquals($t->getCurrentImage(), 'hello.png');
        $this->term_repo->save($t, true);

        $sql = "select WiWoID, WiSource from wordimages";
        $exp = [ "1; hello.png" ];
        DbHelpers::assertTableContains($sql, $exp, "image saved");
    }

    /**
     * @group images
     */
    public function test_save_replace_image()
    {
        $t = new Term($this->spanish, "HOLA");
        $t->setCurrentImage('hello.png');
        $this->assertEquals($t->getCurrentImage(), 'hello.png');
        $this->term_repo->save($t, true);

        $sql = "select WiWoID, WiSource from wordimages";
        $exp = [ "1; hello.png" ];
        DbHelpers::assertTableContains($sql, $exp, "image saved");

        $t->setCurrentImage('there.png');
        $this->assertEquals($t->getCurrentImage(), 'there.png');
        $this->term_repo->save($t, true);

        $exp = [ "1; there.png" ];
        DbHelpers::assertTableContains($sql, $exp, "image replaced");
    }

    /**
     * @group images
     */
    public function test_save_remove_image()
    {
        $t = new Term($this->spanish, "HOLA");
        $t->setCurrentImage('hello.png');
        $this->assertEquals($t->getCurrentImage(), 'hello.png');
        $this->term_repo->save($t, true);

        $sql = "select WiWoID, WiSource from wordimages";
        $exp = [ "1; hello.png" ];
        DbHelpers::assertTableContains($sql, $exp, "image saved");

        $t->setCurrentImage(null);
        $this->assertEquals($t->getCurrentImage(), null);
        $this->term_repo->save($t, true);

        $exp = [ ];
        DbHelpers::assertTableContains($sql, $exp, "image removed");
    }

    // TODO:image_integration_tests Future integration-style tests.
    //
    // Integration tests should remove all images from the userimages
    // folder.  To prevent problems/data loss, there should be a check
    // for some sort of "control file" that the dev has to create in a
    // particular location, or maybe a setting in .env.test/.local, to
    // acknowledge that this will happen and is ok.  Don't want devs
    // to accidentally kill their own personal images.
    //
    // term set current image - downloads if possible (use /public/img/lute.png for tests?)
    // remove term leaves its image in images folder

}
