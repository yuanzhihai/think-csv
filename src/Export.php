<?php
declare ( strict_types = 1 );

namespace yzh52521\thinkCsv;

use League\Csv\CannotInsertRecord;
use League\Csv\Reader;
use League\Csv\Writer;
use SplTempFileObject;
use think\db\Query;
use think\helper\Arr;
use think\Collection;

class Export
{
    /**
     * The default chunk size when looping through the builder results.
     * @var int
     */
    const DEFAULT_CHUNK_SIZE = 1000;

    /**
     * @var array
     */
    protected $config = [];
    /**
     * @var null | Writer
     */
    protected $writer;
    /**
     * @var callable
     */
    private $beforeEachCallback;
    /**
     * @var callable
     */
    private $beforeEachChunkCallback;


    /**
     * Export constructor.
     * @param Writer|null $writer
     */
    public function __construct(Writer $writer = null)
    {
        $this->writer = $writer ?: Writer::createFromFileObject( new SplTempFileObject );
    }

    /**
     * Get a CSV reader.
     *
     * @return Reader
     */
    public function getReader()
    {
        return Reader::createFromString( $this->writer->getContent() );
    }

    /**
     * Get the CSV writer.
     *
     * @return Writer
     */
    public function getWriter()
    {
        return $this->writer;
    }


    /**
     * @param callable $callback
     * @return Export
     */
    public function beforeEach(callable $callback): self
    {
        $this->beforeEachCallback = $callback;
        return $this;
    }

    /**
     * @param $collection
     * @param array $fields
     * @param array $config
     * @return Export
     * @throws CannotInsertRecord
     */
    public function build($collection,array $fields,array $config = []): self
    {
        $this->config = $config;
        $this->addHeader( $this->writer,$this->getHeaderFields( $fields ) );
        $this->addCsvRows( $this->writer,$this->getDataFields( $fields ),$collection );
        return $this;
    }

    /**
     * Callback which is run before processsing each chunk.
     *
     * @param callable $callback
     * @return $this
     */
    public function beforeEachChunk(callable $callback): self
    {
        $this->beforeEachChunkCallback = $callback;
        return $this;
    }

    /**
     * Build the CSV from a builder instance.
     *
     * @param Query $builder
     * @param array $fields
     * @param array $config
     * @return $this
     * @throws CannotInsertRecord
     */
    public function buildFromBuilder(Query $builder,array $fields,array $config = []): self
    {
        $this->config = $config;

        $chunkSize  = Arr::get( $config,'chunk',self::DEFAULT_CHUNK_SIZE );
        $dataFields = $this->getDataFields( $fields );

        $this->addHeader( $this->writer,$this->getHeaderFields( $fields ) );

        $builder->chunk( $chunkSize,function ($collection) use ($dataFields) {
            $callback = $this->beforeEachChunkCallback;

            if ($callback && $callback( $collection ) === false) {
                return;
            }

            $this->addCsvRows( $this->writer,$dataFields,$collection );
        } );

        return $this;
    }

    /**
     * Download the CSV file.
     * @param string|null $filename
     * @return void
     */
    public function download($filename = null): void
    {
        $filename = $filename ?: date( 'Y-m-d_His' ).'.csv';
        $this->writer->output( $filename );
    }

    /**
     * @param array $fields
     * @return array
     */
    private function getHeaderFields(array $fields): array
    {
        return array_values( $fields );
    }

    /**
     * @param array $fields
     * @return array
     */
    private function getDataFields(array $fields): array
    {
        foreach ( $fields as $key => $field ) {
            if (is_string( $key )) {
                $fields[$key] = $key;
            }
        }

        return array_values( $fields );
    }

    /**
     * @param Writer $writer
     * @param array $fields
     * @param Collection $collection
     * @throws CannotInsertRecord
     */
    private function addCsvRows(Writer $writer,array $fields,Collection $collection): void
    {
        foreach ( $collection as $model ) {
            $beforeEachCallback = $this->beforeEachCallback;
            // Call hook
            if ($beforeEachCallback) {
                $return = $beforeEachCallback( $model );
                if ($return === false) {
                    continue;
                }
            }
            if (!Arr::accessible( $model )) {
                $model = collect( $model );
            }
            $csvRow = [];
            foreach ( $fields as $field ) {
                $csvRow[] = Arr::get( $model,$field );
            }
            $writer->insertOne( $csvRow );
        }
    }

    /**
     * @param Writer $writer
     * @param array $headers
     * @throws CannotInsertRecord
     */
    private function addHeader(Writer $writer,array $headers): void
    {
        if (Arr::get( $this->config,'header',true ) !== false) {
            $writer->insertOne( $headers );
        }
    }
}