<?php

namespace Rubix\ML\Regressors;

use Rubix\ML\Learner;
use Rubix\ML\Persistable;
use Rubix\ML\Graph\KDTree;
use Rubix\ML\Datasets\Dataset;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\DataFrame;
use Rubix\ML\Other\Helpers\Stats;
use Rubix\ML\Kernels\Distance\Distance;
use InvalidArgumentException;
use RuntimeException;

/**
 * K-d Neighbors Regressor
 *
 * A fast implementation of KNN Regressor using a K-d tree. The KDN Regressor works
 * by locating the neighborhood of a sample via binary search and then does a brute
 * force search only on the samples close to or within the neighborhood. The main
 * advantage of K-d Neighbors over brute force KNN is speed, however you no longer
 * have the ability to partially train.
 *
 * References:
 * [1] J. L. Bentley. (1975). Multidimensional Binary Seach Trees Used for
 * Associative Searching.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class KDNRegressor extends KDTree implements Learner, Persistable
{
    /**
     * The number of neighbors to consider when making a prediction.
     *
     * @var int
     */
    protected $k;

    /**
     * Should we use the inverse distances as confidence scores when
     * making predictions?
     * 
     * @var bool
     */
    protected $weighted;

    /**
     * @param  int  $k
     * @param  int  $maxLeafSize
     * @param  \Rubix\ML\Kernels\Distance\Distance|null  $kernel
     * @param  bool  $weighted
     * @throws \InvalidArgumentException
     * @return void
     */
    public function __construct(int $k = 3, int $maxLeafSize = 20, ?Distance $kernel = null, bool $weighted = true)
    {
        if ($k < 1) {
            throw new InvalidArgumentException('At least 1 neighbor is required'
                . " to make a prediction, $k given.");
        }

        if ($k > $maxLeafSize) {
            throw new InvalidArgumentException('K cannot be larger than the max'
                . " leaf size, $k given but $maxLeafSize allowed.");
        }

        $this->k = $k;
        $this->weighted = $weighted;

        parent::__construct($maxLeafSize, $kernel);
    }

    /**
     * Return the integer encoded type of estimator this is.
     *
     * @return int
     */
    public function type() : int
    {
        return self::REGRESSOR;
    }

    /**
     * @param  \Rubix\ML\Datasets\Dataset  $dataset
     * @throws \InvalidArgumentException
     * @return void
     */
    public function train(Dataset $dataset) : void
    {
        if (!$dataset instanceof Labeled) {
            throw new InvalidArgumentException('This estimator requires a'
                . ' labeled training set.');
        }

        if ($dataset->typeCount(DataFrame::CONTINUOUS) !== $dataset->numColumns()) {
            throw new InvalidArgumentException('This estimator only works'
                . ' with continuous features.');
        }

        $this->grow($dataset);
    }

    /**
     * Make a prediction based on the nearest neighbors.
     *
     * @param  \Rubix\ML\Datasets\Dataset  $dataset
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @return array
     */
    public function predict(Dataset $dataset) : array
    {
        if (in_array(DataFrame::CATEGORICAL, $dataset->types())) {
            throw new InvalidArgumentException('This estimator only works with'
                . ' continuous features.');
        }

        if ($this->bare()) {
            throw new RuntimeException('Estimator has not been trainied.');
        }

        $predictions = [];

        foreach ($dataset as $sample) {
            list($labels, $distances) = $this->neighbors($sample);

            if ($this->weighted) {
                $weights = [];

                foreach ($distances as $i => $distance) {
                    $weights[] = 1. / (1. + $distance);
                }

                $outcome = Stats::weightedMean($labels, $weights);
            } else {
                $outcome = Stats::mean($labels);
            }

            $predictions[] = $outcome;
        }

        return $predictions;
    }
}
