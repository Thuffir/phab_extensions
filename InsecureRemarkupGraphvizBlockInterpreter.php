<?php

/**
 * !!! WARNING !!!
 *
 * This rule is NOT SECURE! It contains KNOWN VULNERABILITIES which permit an
 * attacker to (at a minimum) disclose information about the system with a
 * specially crafted input.
 *
 * INSTALL THIS RULE AT YOUR OWN RISK.
 */
final class InsecureRemarkupGraphvizBlockInterpreter
  extends PhutilRemarkupBlockInterpreter {

  public function getInterpreterName() {
    return 'dot';
  }

  public function markupContent($content, array $argv) {
    if (!Filesystem::binaryExists('dot')) {
      return $this->markupError(
        pht(
          'Unable to locate the `%s` binary. Install Graphviz.',
          'dot'));
    }

    $width = $this->parseDimension(idx($argv, 'width'));

    $future = id(new ExecFuture('dot -T%s', 'png'))
      ->setTimeout(15)
      ->write(trim($content));

    list($err, $stdout, $stderr) = $future->resolve();

    if ($err) {
      return $this->markupError(
        pht(
          'Execution of `%s` failed (#%d), check your syntax: %s',
          'dot',
          $err,
          $stderr));
    }

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    $file = PhabricatorFile::newFromFileData(
      $stdout,
      array(
        'name' => 'graphviz.png',
        'ttl' => time() + (60 * 60 * 24 * 31),
      ));
    unset($unguarded);

    if ($this->getEngine()->isTextMode()) {
      return '<'.$file->getBestURI().'>';
    }

    $img = phutil_tag(
      'img',
      array(
        'src' => $file->getBestURI(),
        'width' => nonempty($width, null),
      ));
    return phutil_tag_div('phabricator-remarkup-embed-image-full', $img);
  }

  // TODO: This is duplicated from PhabricatorEmbedFileRemarkupRule since they
  // do not share a base class.
  private function parseDimension($string) {
    $string = trim($string);

    if (preg_match('/^(?:\d*\\.)?\d+%?$/', $string)) {
      return $string;
    }

    return null;
  }
}
