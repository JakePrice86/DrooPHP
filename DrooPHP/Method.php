<?php
/**
 * @file
 *   DrooPHP_Method base class.
 * @package
 *   DrooPHP
 */

/**
 * @class
 *   DrooPHP_Method
 *   Base class for a vote counting algorithm.
 */
class DrooPHP_Method {

  /** @var DrooPHP_Count */
  public $count;

  /** @var DrooPHP_Election */
  public $election;

  /** @var int */
  public $quota;

  /** @var int */
  public $num_elected;

  public function __construct(DrooPHP_Count $count) {
    $this->count = $count;
    $this->election = $count->election;
    $this->_calculateQuota();
  }

  /** @todo */
  public function run($round = 1) {
    echo "- COUNTING ROUND $round -\n";
    $election = $this->election;
    $num_seats = $election->getNumSeats();
    $quota = $this->quota;
    $next_round = $round + 1;
    $someone_elected = FALSE;
    if (!isset($activeVote)) {
      $activeVote = $election->getNumBallots();
    }
    $remaining = array();
    foreach ($election->getCandidates() as $cid => $candidate) {
      if ($candidate->state !== DrooPHP_Candidate::STATE_HOPEFUL) {
        // Ignore elected, withdrawn, or defeated candidates.
        continue;
      }
      $votes = $candidate->getVotes($round);
      if ($votes >= $quota || $votes > ($activeVote / ($num_seats - $num_elected + 1))) {
        // The candidate is now elected.
        $candidate->state = DrooPHP_Candidate::STATE_ELECTED;
        $someone_elected = TRUE;
        $this->num_elected++;
        // Calculate the candidate's surplus, and transfer votes for the next round.
        $surplus = $votes - $quota;
        $candidate->log("Elected in round $round.");
        $activeVote -= ($votes < $quota)? $votes : $quota;
        if ($surplus > 0) {
          $this->transferVotes($cid, $surplus, $next_round);
          $candidate->log("A surplus of $surplus votes was transferred to other candidates for round $next_round.");
        }
      }
      else {
        // If the candidate hasn't been elected, add number of votes to $remaining so a comparison can be made for elimination.
        $remaining[$cid] = $votes;
      }
    }
    // If no-one has been elected in this round, eliminate the candidate with the fewest votes.
    if (!$someone_elected) {
      $to_eliminate = NULL;
      foreach ($remaining as $cid => $votes) {
        if (!isset($last) || $votes < $last) {
          $last = $votes;
          $to_eliminate = $cid;
        }
      }
      if ($to_eliminate !== NULL) {
        $cid = $to_eliminate;
        $votes = $remaining[$cid];
        $candidate = $election->getCandidate($cid);
        $candidate->state = DrooPHP_Candidate::STATE_DEFEATED;
        $candidate->log("Defeated in round $round.");
        if ($votes > 0) {
          $this->transferVotes($cid, $votes, $next_round);
          $candidate->log("$votes votes were transferred from defeated candidate '$cid' to other candidates for round $next_round.");
        }
      }
    }
    // Proceed to the next round or stop if the election is complete.
    if ($this->isComplete()) {
      return TRUE;
    }
    else {
      $this->run($next_round);
    }
  }

  /**
   * Test whether the election is complete.
   *
   * @return bool
   */
  public function isComplete() {
    $election = $this->election;
    $num_seats = $election->getNumSeats();
    $num_candidates = $election->getNumCandidates();
    $must_be_elected = $num_seats;
    if ($num_seats > $num_candidates) {
      $must_be_elected = $num_candidates;
    }
    return $this->num_elected >= $must_be_elected;
  }

  /**
   * Transfer the votes from a successful candidate to the other hopeful ones.
   *
   * @param mixed $from_cid
   * @param int $surplus
   * @param int $round
   */
  public function transferVotes($from_cid, $surplus, $round) {
    echo "Attempting transfer from $from_cid\n";
    $count = $this->count;
    $election = $this->election;
    $votes = $count->getVoteRatio($round, $from_cid);
    $totalVotes = 0;
    foreach ($votes as $cid => $num) {
      $totalVotes += $num;
    }
    if ($totalVotes == 0) {
      // Other candidates are not ranked after $from_cid in this round.
      return;
    }
    foreach ($votes as $cid => $num) {
      $dest_candidate = $election->getCandidate($cid);
      if ($dest_candidate->state === DrooPHP_Candidate::STATE_HOPEFUL) {
        $transferNum = floor(($num / $totalVotes) * $surplus);
        $dest_candidate->addVotes($transferNum);
        $dest_candidate->log("Received $transferNum transferred votes from candidate '$from_cid' for round $round.");
      }
    }
  }

  /**
   * Calculate the minimum number of votes a candidate needs in order to be
   * elected.
   *
   * By default this is the Droop quota:
   *   floor((number of valid ballots / (number of seats + 1)) + 1)
   *
   * @return int
   */
  protected function _calculateQuota() {
    $election = $this->election;
    $num = ($election->getNumBallots() / ($election->getNumSeats() + 1)) + 1;
    $quota = floor($num);
    $this->quota = $quota;
    return $quota;
  }

}