<?php namespace Bbt;

use MongoDB\Collection;

class MongoDbCollection extends Collection 
{
    private $batchBuffer;
    private $batchBufferCount;

    /**
	 * @param       $id
	 * @param array $options
	 *
	 * @return array|null|object
	 */
	public function findById( $id, $options=[])
	{
		return $this->findOne(['_id' => $id], $options);
	}

	/**
	 * @param $filter
	 *
	 * @return bool true if at least one matching document exists
	 */
	public function existsFound( array $filter ): bool
	{
		$dFound = $this->findOne($filter, ['projection' => ['_id' => 1]]);
		
		return !empty($dFound);
	}

    public function assuredUpdateById( $id, array $update, $options=[] ): \MongoDB\UpdateResult
    {
        $options['writeConcern'] = Db::getWriteConcernAll();

        if( is_array($id) ) return $this->updateMany([ '_id' => [ '$in' => $id ] ], $update, $options);

        return $this->updateOne(['_id' => $id], $update, $options);
    }

    public function assuredUpdateOne($filter, $update, array $options = []): \MongoDB\UpdateResult
    {
        $options['writeConcern'] = Db::getWriteConcernAll();

        return $this->updateOne($filter, $update, $options);
    }

    public function assuredUpdateMany($filter, $update, array $options = []): \MongoDB\UpdateResult
    {
        $options['writeConcern'] = Db::getWriteConcernAll();

        return $this->updateMany($filter, $update, $options);
    }

    public function assuredFindOneAndUpdate($filter, $update, array $options = []): ?array
    {
        $options['writeConcern'] = Db::getWriteConcernAll();

        return $this->findOneAndUpdate($filter, $update, $options);
    }

    public function assuredInsertOne($document, array $options = []): \MongoDB\InsertOneResult
    {
        $options['writeConcern'] = Db::getWriteConcernAll();

        return $this->insertOne($document, $options);
    }

    public function assuredInsertMany(array $documents, array $options = []): \MongoDB\InsertManyResult
    {
        $options['writeConcern'] = Db::getWriteConcernAll();

        return $this->insertMany($documents, $options);
    }

    /**
     * invoke for each document to be inserted
     * invoke without parameters once to flush remaining batch
     *
     * @param null $document
     * @param array $options
     * @param int $batchSize
     */
    public function batchInsert($document = null, array $options = [], int $batchSize = 1000): void
    {
        if (null == $this->batchBuffer) {
            $this->batchBuffer      = [];
            $this->batchBufferCount = 0;
        }

        if( null !== $document ) {
            $this->batchBuffer[] = $document;
            $this->batchBufferCount++;
        }

        if( null === $document || $this->batchBufferCount >= $batchSize) {
            if (!empty($this->batchBuffer)) {
                $this->insertMany($this->batchBuffer, $options);
                $this->batchBuffer      = [];
                $this->batchBufferCount = 0;
            }
        }
    }


    /**
	 * @param       $id
	 * @param       $update
	 * @param array $options
	 * @return \MongoDB\UpdateResult
	 */
	public function updateById( $id, array $update, $options=[] ): \MongoDB\UpdateResult
	{
		if( is_array($id) ) return $this->updateMany([ '_id' => [ '$in' => $id ] ], $update, $options);
		
		return $this->updateOne(['_id' => $id], $update, $options);
	}

	/**
	 * @param       $id
	 * @param array $options
	 * @return \MongoDB\DeleteResult
	 */
	public function deleteById( $id, $options=[] ): \MongoDB\DeleteResult
	{
		return $this->deleteOne(['_id' => $id], $options);
	}
}