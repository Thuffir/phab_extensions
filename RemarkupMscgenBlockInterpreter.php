<?php

final class RemarkupMscgenBlockInterpreter
  extends PhutilRemarkupBlockInterpreter {

  public function getInterpreterName() {
    return 'msc';
  }

  public function markupContent($content, array $argv) {
    if (!Filesystem::binaryExists('mscgen')) {
      return $this->markupError(
        pht(
          'Unable to locate the `%s` binary. Install Mscgen.',
          'mscgen'));
    }

    $width = $this->parseDimension(idx($argv, 'width'));

    $future = id(new ExecFuture('mscgen -T%s -i- -o-', 'png'))
      ->setTimeout(15)
      ->write(trim($content));

    list($err, $stdout, $stderr) = $future->resolve();

    if ($err) {
      return $this->markupError(
        pht(
          'Execution of `%s` failed (#%d), check your syntax: %s',
          'mscgen',
          $err,
          $stderr));
    }

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    $file = PhabricatorFile::newFromFileData(
      $stdout,
      array(
        'name' => 'mscgen.png',
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
