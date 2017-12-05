<?hh // strict
/*
 *  Copyright (c) 2004-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the BSD-style license found in the
 *  LICENSE file in the root directory of this source tree. An additional grant
 *  of patent rights can be found in the PATENTS file in the same directory.
 *
 */

namespace Facebook\GFM;

use namespace HH\Lib\{C, Str, Vec};

// TODO: fix namespace support in XHP, use that :'(
class HTMLRenderer extends Renderer<string> {

  protected static function escapeContent(string $text): string {
    return _Private\plain_text_to_html($text);
  }

  protected static function escapeAttribute(string $text): string {
    return _Private\plain_text_to_html_attribute($text);
  }

  <<__Override>>
  protected function renderNodes(vec<ASTNode> $nodes): string {
    return $nodes
      |> Vec\map($$, $node ==> $this->render($node))
      |> Str\join($$, '');
  }

  <<__Override>>
  protected function renderResolvedNode(ASTNode $node): string {
    if ($node instanceof RenderableAsHTML) {
      return $node->renderAsHTML($this->getContext(), $this);
    }
    return parent::renderResolvedNode($node);
  }

  <<__Override>>
  protected function renderBlankLine(): string {
    return "\n";
  }

  <<__Override>>
  protected function renderBlockQuote(Blocks\BlockQuote $node): string {
    return $node->getChildren()
      |> Vec\map($$, $child ==> $this->render($child))
      |> Str\join($$, '')
      |> '<blockquote>'.$$.'</blockquote>';
  }

  <<__Override>>
  protected function renderCodeBlock(Blocks\CodeBlock $node): string {
    $extra = '';
    $info = $node->getInfoString();
    if ($info !== null) {
      $first = C\firstx(Str\split($info, ' '));
      $extra = ' class="language-'.self::escapeAttribute($first).'"';
    }
    return self::escapeContent($node->getCode())
      |> '<pre><code'.$extra.'>'.$$."\n</code></pre>";
  }

  <<__Override>>
  protected function renderHeading(Blocks\Heading $node): string {
    $level = $node->getLevel();
    return $node->getHeading()
      |> Vec\map($$, $child ==> $this->render($child))
      |> Str\join($$, '')
      |> sprintf("<h%d>%s</h%d>", $level, $$, $level);
  }

  <<__Override>>
  protected function renderHTMLBlock(Blocks\HTMLBlock $node): string {
    return $node->getCode();
  }

  <<__Override>>
  protected function renderLinkReferenceDefinition(
    Blocks\LinkReferenceDefinition $def,
  ): string {
    return '';
  }

  <<__Override>>
  protected function renderListItem(Blocks\ListItem $node): string {
    $children = $node->getChildren();
    $child = C\first($children);
    if (C\count($children) === 1 && $child instanceof Blocks\Paragraph) {
      $children = $child->getContents();
    }
    return $children
      |> Vec\map($$, $child ==> $this->render($child))
      |> Str\join($$, "\n")
      |> '<li>'.$$.'</li>';
  }

  <<__Override>>
  protected function renderListOfItems(Blocks\ListOfItems $node): string {
    $start = $node->getFirstNumber();
    if ($start === null) {
      $start = '<ul>';
      $end = '</ul>';
    } else {
      $start = sprintf('<ol start="%d">', $start);
      $end = '</ol>';
    }
    return $node->getItems()
      |> Vec\map($$, $item ==> $this->render($item))
      |> Str\join($$, "\n")
      |> $start."\n".$$."\n".$end."\n";
  }

  <<__Override>>
  protected function renderParagraph(Blocks\Paragraph $node): string {
    return '<p>'.$this->renderNodes($node->getContents()).'</p>';
  }

  <<__Override>>
  protected function renderTableExtension(Blocks\TableExtension $node): string {
    $html = "<table>\n".$this->renderTableHeader($node);

    $data = $node->getData();
    if (C\is_empty($data)) {
      return $html."</table>\n";
    }
    $html .= "\n<tbody>";

    $row_idx = -1;
    foreach ($data as $row) {
      ++$row_idx;
      $html .= "\n".$this->renderTableDataRow($node, $row_idx, $row);
    }
    return $html.'</tbody></table>';
  }

  protected function renderTableHeader(Blocks\TableExtension $node): string {
    $html = "<thead>\n<tr>\n";

    $alignments = $node->getColumnAlignments();
    $header = $node->getHeader();
    for ($i = 0; $i < C\count($header); ++$i) {
      $cell = $header[$i];
      $alignment = $alignments[$i];
      if ($alignment !== null) {
        $alignment = ' align="'.$alignment.'"';
      }
      $html .=
        '<th'.$alignment.'>'.
        $this->renderNodes($cell).
        "</th>\n";
    }
    $html .= '</thead>';
    return $html;
  }

  protected function renderTableDataRow(
    Blocks\TableExtension $table,
    int $row_idx,
    Blocks\TableExtension::TRow $row,
  ): string {
    $html = "<tr>";
    for ($i = 0; $i < C\count($row); ++$i) {
      $cell = $row[$i];

      $html .= "\n".$this->renderTableDataCell($table, $row_idx, $i, $cell);
    }
    $html .= "\n</tr>";
    return $html;
  }

  protected function renderTableDataCell(
    Blocks\TableExtension $table,
    int $row_idx,
    int $col_idx,
    Blocks\TableExtension::TCell $cell,
  ): string {
    $alignment = $table->getColumnAlignments()[$col_idx];
    if ($alignment !== null) {
      $alignment = ' align="'.$alignment.'"';
    }
    return
      "<td".$alignment.'>'.
      $this->renderNodes($cell).
      "</td>";
  }

  <<__Override>>
  protected function renderThematicBreak(): string {
    return "<hr />\n";
  }

  <<__Override>>
  protected function renderAutoLink(Inlines\AutoLink $node): string {
    $href = self::escapeAttribute($node->getDestination());
    $text = self::escapeContent($node->getText());
    return '<a href="'.$href.'">'.$text.'</a>';
  }

  <<__Override>>
  protected function renderInlineWithPlainTextContent(Inlines\InlineWithPlainTextContent $node): string {
    return self::escapeContent($node->getContent());
  }

  <<__Override>>
  protected function renderCodeSpan(Inlines\CodeSpan $node): string {
    return '<code>'.self::escapeContent($node->getCode()).'</code>';
  }

  <<__Override>>
  protected function renderEmphasis(Inlines\Emphasis $node): string {
    $tag = $node->isStrong() ? 'strong' : 'em';
    return $node->getContent()
      |> Vec\map($$, $item ==> $this->render($item))
      |> Str\join($$, '')
      |> '<'.$tag.'>'.$$.'</'.$tag.'>';
  }

  <<__Override>>
  protected function renderHardLineBreak(): string {
    return "<br />\n";
  }

  <<__Override>>
  protected function renderImage(Inlines\Image $node): string {
    $title = $node->getTitle();
    if ($title !== null) {
      $title = ' title="'.self::escapeAttribute($title).'"';
    }
    $src = self::escapeAttribute($node->getSource());
    $text = $node->getDescription()
      |> Vec\map($$, $child ==> $child->getContentAsPlainText())
      |> Str\join($$, '');
    $alt = ($text === '')
      ? '' : ' alt="'.self::escapeAttribute($text).'"';
    return '<img src="'.$src.'"'.$alt.$title.' />';
  }

  <<__Override>>
  protected function renderLink(Inlines\Link $node): string {
    $title = $node->getTitle();
    if ($title !== null) {
      $title = ' title="'.self::escapeAttribute($title).'"';
    }
    $href = self::escapeAttribute($node->getDestination());
    $text = $node->getText()
      |> Vec\map($$, $child ==> $this->render($child))
      |> Str\join($$, '');
    return '<a href="'.$href.'"'.$title.'>'.$text.'</a>';
  }

  <<__Override>>
  protected function renderRawHTML(Inlines\RawHTML $node): string {
    return $node->getContent();
  }

  <<__Override>>
  protected function renderSoftLineBreak(): string {
    return "\n";
  }

  <<__Override>>
  protected function renderStrikethroughExtension(Inlines\StrikethroughExtension $node): string {
    $children = $node->getChildren()
      |> Vec\map($$, $child ==> $this->render($child))
      |> Str\join($$, '');
    return '<del>'.$children.'</del>';
  }
}
