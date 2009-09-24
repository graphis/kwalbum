<?php
/**
 *
 *
 * @author Tim Redmond <kweejee@tummycaching.com>
 * @copyright Copyright 2009 Tim Redmond
 * @license GNU General Public License version 3 <http://www.gnu.org/licenses/>
 * @package kwalbum
 * @since Aug 24, 2009
 */
?>
<div class="box">
<form action="<?php echo URL ?>~user/upload" method="post" enctype="multipart/form-data" autocomplete="off">
<input type='hidden' name='overLimit' value='no'/>
	<p>
		<table>
		<tr>
		<td colspan='2'><b>Information for all pictures being uploaded</b></td>
		<td>Visibility: <select name='vis' id='vis'>
			<option value='0'>Public</option>
			<option value='1'>Members Only</option>
			<option value='2'>Privileged Only</option>
			<?php echo ($user_is_admin ? "<option value='3'>Admin Only</option>" : null) ?></select>
		</td>
		</tr>
		<tr>
		<td>
			<label>Location:</label>
			<input type="text" class="text" name="loc" id="loc" value="<?php echo $location?>" size="15" />
		</td>
		<td>
			<label>Tags:</label>
			<input type="text" class="text" name="tags" id="tags" value="<?php echo $tags?>" size="20" />
		</td>
		<td>Date if not found on picture: <input type="text" class="text" name="date" id="date" value="<?php echo $date ?>" size="8" /></td>
	</tr></table>
	</p>
	<p>
		<small>Allowed filetypes are jpg, gif, and png<?php /*, wmv,
		<a href="http://filext.com/file-extension/divx" target='_blank'>divx</a>, mpeg,
		<a href="http://filext.com/file-extension/mp4" target='_blank'>mp4</a>,txt, html,
		<a href="http://filext.com/file-extension/gpx" target='_blank'>gpx</a>, xml, zip, mp3,
		wav, <a href="http://filext.com/file-extension/odt" target='_blank'>odt</a>, ods,
		<a href="http://filext.com/file-extension/ogg">ogg</a>, doc, and <a href="http://filext.com/file-extension/flv">flv</a>*/?>
		. Files larger than <?php echo (ini_get('memory_limit') / 10); ?>M may not upload correctly.</small><br/>

		<input type="file" name="fileInput" id="fileInput" />
		<a href="javascript:kwalbumUpload();">Upload Files</a> | <a href="javascript:$('#fileInput').uploadifyClearQueue();">Clear Queue</a>
	</p>
</form>
</div>