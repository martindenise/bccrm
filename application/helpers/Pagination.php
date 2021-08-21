<?php

class Zend_View_Helper_Pagination extends Zend_View_Helper_Abstract
{
	public function Pagination($config)
    {
    	$base_url = $config['base_url'];
    	$num_items = $config['num_items'];
    	$per_page = $config['per_page'];
    	$page_no = $config['page_no'];
    	$paginationDivClass = !empty($config['class']) ? $config['class'] : '';
    	$paginationDivStyle = !empty($config['style']) ? $config['style'] : '';
    	// add results to link ?
    	$addPerPageParam = ($per_page != DEFAULT_RESULTS_PER_PAGE) ? '&results='.$per_page : '';
    	
    	$start_item = ($page_no-1)*$per_page;
		$total_pages = ceil($num_items/$per_page);
		if ( $total_pages <= 1 ) return '';
		$on_page = floor($start_item / $per_page) + 1;
		$page_string = '';
		if ( $total_pages > 6 )
		{
			$init_page_max = ( $total_pages > 3 ) ? 3 : $total_pages;
			for($i = 1; $i < $init_page_max + 1; $i++)
			{
				$page_string .= ( $i == $on_page ) ? '<b>' . $i . '</b>' : '<a href="' . $base_url . "page=" . ( $i )  . $addPerPageParam .'">' . $i . '</a>';
				if ( $i <  $init_page_max )
					$page_string .= "&nbsp;&nbsp; ";
			}
			if ( $total_pages > 3 )
			{
				if ( $on_page > 1  && $on_page < $total_pages )
				{
					$page_string .= ( $on_page > 5 ) ? ' ... ' : '&nbsp;&nbsp; ';
					$init_page_min = ( $on_page > 4 ) ? $on_page : 5;
					$init_page_max = ( $on_page < $total_pages - 4 ) ? $on_page : $total_pages - 4;
					for($i = $init_page_min - 1; $i < $init_page_max + 2; $i++)
					{
						$page_string .= ($i == $on_page) ? '<b>' . $i . '</b>' : '<a href="' . $base_url . "page=" . ( $i )  . $addPerPageParam .'">' . $i . '</a>';
						if ( $i <  $init_page_max + 1 )
							$page_string .= '&nbsp;&nbsp; ';
					}
					$page_string .= ( $on_page < $total_pages - 4 ) ? ' ... ' : '&nbsp;&nbsp; ';
				}
				else
					$page_string .= ' ... ';
				for($i = $total_pages - 2; $i < $total_pages + 1; $i++)
				{
					$page_string .= ( $i == $on_page ) ? '<b>' . $i . '</b>'  : '<a href="' . $base_url . "page=" . ( $i )  . $addPerPageParam .'">' . $i . '</a>';
					if( $i <  $total_pages )
						$page_string .= "&nbsp;&nbsp; ";
				}
			}
		}
		else
		{
			for($i = 1; $i < $total_pages + 1; $i++)
			{
				$page_string .= ( $i == $on_page ) ? '<b>' . $i . '</b>' : '<a href="' . $base_url . "page=" . ( $i )  . $addPerPageParam .'">' . $i . '</a>';
				if ( $i <  $total_pages )
					$page_string .= '&nbsp;&nbsp; ';
			}
		}
		
		if ( $on_page > 1 )
			$page_string = '<a href="' . $base_url . 'page=1'. $addPerPageParam .'">|<<</a>&nbsp;&nbsp; <a href="' . $base_url . "page=" . ( $page_no - 1 )  . $addPerPageParam .'">' . '<<' . '</a>  &nbsp;&nbsp;Page&nbsp;&nbsp; ' . $page_string;
		else $page_string = '|<<</a>&nbsp;&nbsp; << &nbsp;&nbsp;Page&nbsp;&nbsp; ' . $page_string;
		if ( $on_page < $total_pages )
			$page_string .= ' &nbsp;&nbsp;of&nbsp;&nbsp;' . $total_pages . ' &nbsp;&nbsp;<a href="' . $base_url . "page=" . ( $page_no + 1 )  . $addPerPageParam .'">' . '>>' . '</a>&nbsp;&nbsp; <a href="' . $base_url . 'page=' . (( $i - 1 )) . $addPerPageParam .'">>>|</a>';
		else $page_string .= ' &nbsp;&nbsp;of&nbsp;&nbsp;' . $total_pages . ' &nbsp;&nbsp; >> &nbsp;&nbsp; >>|';
	
		$page_string = '<div class="'.$paginationDivClass.'" style="'.$paginationDivStyle.'">'.$page_string.'</div>';
		
		return $page_string;
    }
}

?>