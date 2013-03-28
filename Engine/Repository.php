<?php

namespace TagCloud\Engine;

use Doctrine\DBAL\Connection;
use PDO;

class Repository
{
    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function getTaxonomyGroupFor($contentType, $taxonomyType)
    {
        $stmt = $this
            ->conn
            ->createQueryBuilder()
            ->select('bt.slug')
            ->addSelect('COUNT(bt.id) AS count')
            ->from('bolt_taxonomy', 'bt')
            ->groupBy('bt.slug')
            ->where('bt.taxonomytype = :taxonomyType')
            ->andWhere('bt.contenttype = :contentType')
            ->setParameters(array(
                ':taxonomyType' => $taxonomyType,
                ':contentType' => $contentType
            ))
            ->execute();

        $tags = array();
        while(false !== ($row = $stmt->fetch(PDO::FETCH_NUM))) {
            $tags[$row[0]] = $row[1];
        }

        return $tags;
    }
}