<?php

  $fprefix = "";
  function load_gal_data($galname, $assoc=false)
  {
      global $fprefix;
      $pics = array();
      $galfilename = "./" . $fprefix . $galname . "info.txt";
      //echo "loading " . $galfilename . " assoc = " . $assoc;
      if (file_exists($galfilename))
      {
          $galfile = fopen($galfilename, "r");
          
          while(!feof($galfile))
          {
              $picdesc = trim(fgets($galfile));
              if(!feof($galfile) && $picdesc[0]!="#")
              {
                  $item = explode(":", $picdesc, 6);
                  if($assoc) // make array associative to filename
                      $pics[$item[0]]=array_slice($item,1);
                  else // just a normal array
                      array_push($pics, $item);
              }
          }
          fclose($galfile);
      }
      return $pics;
  }
  
  function fname($filename)
  {
      $p = strrpos($filename , ".");
      
      if($p === false)
          return $filename;
      else
          return substr($filename, 0, $p);
  }
  
  function fext($filename, $defext="jpg")
  {
      $p = strrpos($filename , ".");
      
      if($p === false)
          return $defext;
      else
          return substr($filename, $p+1);
  }
  
  // Gallery data file format:
  // filename:width:height:title:description[:buylink]
  // (width and heigh ignored for gallery list)
  // filename doesn't include the .jpg
  // thumbnails set to 80x80
  // thumbnail names based on image file name (filename_th.jpg)
  // 
  // A [show]info.txt describes all the galleries ("sets")
  // where [show] is the ?show= parameter
  // each "filename" for a gallery is a sub-directory
  // "filename_th.jpg" in the current directory is the thumbnail for the gallery
  // in each sub directory, a galinfo.txt describes the pictures.
  // pictures filename.jpg and filename_th.jpg exist in the sub-directory
  //
  // There is also a top-level file called sectioninfo.txt that lists
  // all the sections that can be specified by the show parameter.
  // "filename" is the "show" parameter and "title" is the section title.
  
  $ingallery = false;
  $curgal = 0;
  $numgals = 0;
  $numpics = 0;
  $picstart = 0;
  $curpic = 0;
  $gals = array();
  $pics = array();
  $show = "";
  $goodshow = true;
  $sections = array();
  
  $wraplinks = true;
  
  $phpname = "gallery.php";
  $picsperpage = 9;
  $thumbwidth = 80;
  $thumbheight = 80;

  $indexctr = 0;
  
  function init($_phpname, $_picsperpage, $_thumbwidth, $_thumbheight, $_fprefix="", $_wraplinks=true)
  {
      global $phpname, $picsperpage, $thumbwidth, $thumbheight;
      global $ingallery, $curgal, $numgals, $numpics;
      global $picstart, $curpic, $gals, $pics, $wraplinks, $indexctr;
      global $show, $goodshow, $sections, $fprefix;
      
      //chdir(dirname(__FILE__)); // do everything relative to the script
      
      $fprefix = $_fprefix;
      $phpname = $_phpname;
      $picsperpage = $_picsperpage;
      $thumbwidth = $_thumbwidth;
      $thumbheight = $_thumbheight;
      $wraplinks = $_wraplinks;
      
      // load the section info.
      $sections = load_gal_data("section", true);
          
      // load the list of galleries
      if (array_key_exists("show", $_GET))
      {
          $show=$_GET["show"];
          if(isset($sections[$show]) || array_key_exists("testmode", $_GET) || $show == "video") 
          {
              $gals = load_gal_data($show);
              $numgals = sizeof($gals);
          }
      }
      
      // have we loaded any galleries? if not, pick a random one
      if($numgals <= 0)
      {
          $goodshow = false;
          $show = array_rand($sections);
          //echo "no section, showing " . $show . " ... ";
          $gals = load_gal_data($show);
          $numgals = sizeof($gals);
      }
      
      // try to access the gallery number specified in the URL
      if (array_key_exists("gallery", $_GET))
      {
          $curgal = (int)$_GET["gallery"];
          if( $curgal >=0 && $curgal < $numgals)
              $pics = load_gal_data(fname($gals[$curgal][0]) . "/gal");
          
          if(sizeof($pics))
              $ingallery = true;
      }
    
      // if no gallery specified, or improper gallery, load a random one (for the random pic)
      if (!$ingallery)
      {
          // count the number of pics in each gallery first to weigh the randomizer properly
          $totalpics = 0;
          for($g=0; $g<$numgals; $g++)
          {
              $totalpics += sizeof(load_gal_data(fname($gals[$g][0]) . "/gal"));
              $galpicindex[$g] = $totalpics;
          }
          // choose a "dummy" random pic just to get the right gallery
          $rndpic = rand(0,$totalpics-1);
          //echo "rndpic is " . $rndpic;
          // now find the appropriate gallery
          for($curgal=0; $curgal<$numgals; $curgal++)
          {
              //echo " ... gallery " . $curgal . " limit is " . $galpicindex[$curgal];
              if($rndpic < $galpicindex[$curgal])
                  break;
          }
          $pics = load_gal_data(fname($gals[$curgal][0]) . "/gal");
      }
      
      $numpics = sizeof($pics);
      
      // now get the current pic number or a random pic if not in a gallery
      if ($ingallery)
      {
          if(array_key_exists("pic", $_GET))
              $curpic = (int)$_GET["pic"];
              
          if($curpic < 0 || $curpic >= $numpics)
              $curpic = 0;
      }
      else
          $curpic = rand(0, $numpics-1);
    
      // load the picstart value if it exists
      if (array_key_exists("picstart", $_GET))
      {
          $picstart = (int)$_GET["picstart"];
          if ($picstart < 0)
              $picstart = 0;
          if ($ingallery && $picstart >= $numpics)
              $picstart = $numpics - 1;
          if (!$ingallery && $picstart >= $numgals)
              $picstart = $numgals - 1;
      }
      else if ($ingallery)
      {
          $picstart = (int)floor($curpic / $picsperpage)*$picsperpage;
      }
      
      $indexctr = $picstart;
  }
  
  // returns URL to link to currently displayed picture
  function curpiclink()
  {
      global $phpname, $show, $curgal, $curpic, $picsperpage;
      
      $newpicstart = (int)floor($curpic / $picsperpage)*$picsperpage;
      return $phpname . '?show=' . $show . '&amp;gallery=' . $curgal .
             '&amp;pic=' . $curpic;
  }
  
  function curpicgaltitle()
  {
      global $gals, $curgal;
      return $gals[$curgal][3];
  }
  
  function curpicgaldesc()
  {
      global $gals, $curgal;
      return $gals[$curgal][4];
  }

  // total number of galleries
  function galleries()
  {
      global $numgals;
      
      return $numgals;
  }

  // current gallery
  function curgallery()
  {
      global $curgal;
      
      return $curgal + 1;
  }
  
  // returns $then if in gallery, $else if not
  function ifingallery($then, $else)
  {
      global $ingallery, $goodshow;
      
      if($ingallery && $goodshow)
          return $then;
      else
          return $else;
  }
  
  // returns the gallery title string, or the $nogal value
  function galtitle($nogal = "")
  {
      global $ingallery;
      
      if($ingallery)
          return curpicgaltitle();
      else
          return $nogal;
  }
  
  function galdesc($nogal = "")
  {
      global $ingallery; 
      
      if($ingallery)
          return curpicgaldesc();
      else
          return $nogal;
  }
  
  // returns the section title string, or the $badsec value
  function sectitle($badsec = "")
  {
      global $show, $goodshow, $sections;   
         
      if($goodshow)
          return $sections[$show][2];
      else
          return $badsec;
  }
  
  function secdesc($badsec = "")
  {
      global $show, $goodshow, $sections;   
         
      if($goodshow)
          return $sections[$show][3];
      else
          return $badsec;
  }
  
  function pictitle()
  {
      global $pics, $curpic;
      return $pics[$curpic][3];
  }
  
  function picdesc()
  {
      global $pics, $curpic;
      return $pics[$curpic][4];
  }
  function pichaslink()
  {
      global $pics, $curpic;
      return count($pics[$curpic]) == 6;
  }
  function piclink()
  {
      global $pics, $curpic;
      return $pics[$curpic][5];
  }
  
  // returns a linked image for the index thumbnail, with $imgopt, or $nopic if no pic
  function indexthumb($imgopt = "", $nopic = "")
  {
      global $ingallery, $indexctr, $numpics, $numgals;
      global $show, $phpname, $curgal, $picstart, $gals, $pics;
      global $thumbwidth, $thumbheight, $goodshow;
      global $fprefix;
      
      $val = $nopic;
      if(!$goodshow)
          return $val;
      
      if ($ingallery && $indexctr < $numpics)
          $val= '<a href="' . $phpname . '?show=' . $show . '&amp;gallery=' . $curgal .
                '&amp;pic=' . $indexctr . '">' .
                '<img src="' . $fprefix . fname($gals[$curgal][0]) . '/' . fname($pics[$indexctr][0]) . '_th.' . fext($pics[$indexctr][0]) . '" ' .
                'width="' . $thumbwidth . '" height="' . $thumbheight . '" ' . $imgopt . '></a>';
      else if(!$ingallery && $indexctr < $numgals)
          $val= '<a href="' . $phpname . '?show=' . $show . '&amp;gallery=' . $indexctr . '">' .
                '<img src="' . $fprefix . fname($gals[$indexctr][0]) . '_th.' . fext($gals[$indexctr][0]) . '" ' .
                'width="' . $thumbwidth . '" height="' . $thumbheight . '" ' . $imgopt . '></a>';
               
      $indexctr++;
      return $val;
  }
  
  // returns title of next thumbnail
  function indextitle()
  {
      global $ingallery, $indexctr, $numpics, $numgals;
      global $gals, $pics, $goodshow;
      
      if(!$goodshow)
          return "";
      
      if ($ingallery && $indexctr < $numpics)
          return $pics[$indexctr][3];
      else if(!$ingallery && $indexctr < $numgals)
          return $gals[$indexctr][3];
  }
  
  // if the next index pic is of the current pic, return $then, else return $else
  function ifiscurpic($then, $else)
  {
      global $ingallery, $indexctr, $curpic;
      
      if($ingallery && $indexctr == $curpic)
          return $then;
      else
          return $else;
  }

  // if the next index pic exists
  function ifpicexists($then, $else)
  {
      global $ingallery, $indexctr, $numpics, $numgals;
      
      if(($ingallery && $indexctr < $numpics) ||
         (!$ingallery && $indexctr < $numgals))
          return $then;
      else
          return $else;
  }

  // total number of pages
  function pages()
  {
      global $ingallery, $numpics, $picsperpage, $numgals;

      if($ingallery)
          $numitems = $numpics;
      else
          $numitems = $numgals;
      
      return floor(($numitems - 1) / $picsperpage) + 1;
  }

  // current page
  function curpage()
  {
      global $picstart, $picsperpage;
      
      return floor($picstart / $picsperpage) + 1;
  }
  
  // if there is a "next page"
  function ifnextpage($then, $else)
  {
      global $picstart, $picsperpage, $ingallery, $numpics, $numgals;
      global $wraplinks;
      
      if($ingallery)
          $numitems = $numpics;
      else
          $numitems = $numgals;
      
      $nextpicstart = $picstart+$picsperpage;
      
      if(($wraplinks && $numitems > $picsperpage) ||
         ($nextpicstart < $numitems))
          return $then;
      else
          return $else;
  }
  
  function ifprevpage($then, $else)
  {
      global $picstart, $picsperpage, $ingallery, $numpics, $numgals;
      global $wraplinks;
      
      if($ingallery)
          $numitems = $numpics;
      else
          $numitems = $numgals;
      
      $prevpicstart = $picstart-$picsperpage;
      
      if(($wraplinks && $numitems > $picsperpage) ||
         ($prevpicstart >= 0))
          return $then;
      else
          return $else;
  }
  
  function ifnextpic($then, $else)
  {
      global $curpic, $numpics;
      global $wraplinks;
      
      if(($wraplinks && $numpics > 1 ) ||
         ($curpic+1 < $numpics))
          return $then;
      else
          return $else;
  }
  
  function ifprevpic($then, $else)
  {
      global $curpic, $numpics;
      global $wraplinks;
      
      if(($wraplinks && $numpics > 1 ) ||
         ($curpic-1 >= 0))
          return $then;
      else
          return $else;
  }
  
  function ifnextgal($then, $else)
  {
      global $curgal, $numgals;
      global $wraplinks;
      
      if(($wraplinks && $numgals > 1 ) ||
         ($curgal+1 < $numgals))
          return $then;
      else
          return $else;
  }
  
  function ifprevgal($then, $else)
  {
      global $curgal, $numgals;
      global $wraplinks;
      
      if(($wraplinks && $numgals > 1 ) ||
         ($curgal-1 >= 0))
          return $then;
      else
          return $else;
  }
  
  // returns URL for next page link
  function nextpagelink()
  {
      global $picstart, $picsperpage, $ingallery;
      global $show, $phpname, $curgal, $curpic, $numpics, $numgals;
      
      $nextpicstart = $picstart+$picsperpage;
      if(($ingallery && $nextpicstart >= $numpics) ||
         (!$ingallery && $nextpicstart >= $numgals))
          $nextpicstart = 0;
      
      if($ingallery)
          return $phpname . '?show=' . $show . '&amp;gallery=' . $curgal .
                 '&amp;pic=' . $curpic .
                 '&amp;picstart=' . $nextpicstart;
      else
          return $phpname . '?show=' . $show . '&amp;picstart=' . $nextpicstart;
  }
  
  function firstpagelink()
  {
      global $ingallery;
      global $show, $phpname, $curgal, $curpic;
      
      if($ingallery)
          return $phpname . '?show=' . $show . '&amp;gallery=' . $curgal .
                 '&amp;pic=' . $curpic . '&amp;picstart=0';
      else
          return $phpname . '?show=' . $show . '&amp;picstart=0';
  }
  
  function prevpagelink()
  {
      global $picstart, $picsperpage, $ingallery;
      global $show, $phpname, $curgal, $curpic, $numpics, $numgals;
      
      $nextpicstart = $picstart-$picsperpage;
      if($nextpicstart < 0)
      {
          if($ingallery)
              $nextpicstart = (int)floor( ($numpics-1) / $picsperpage)*$picsperpage;
          else
              $nextpicstart = (int)floor( ($numgals-1) / $picsperpage)*$picsperpage;
      }
      
      if($ingallery)
          return $phpname . '?show=' . $show . '&amp;gallery=' . $curgal .
                 '&amp;pic=' . $curpic .
                 '&amp;picstart=' . $nextpicstart;
      else
          return $phpname . '?show=' . $show . '&amp;picstart=' . $nextpicstart;
  }
  
  function nextpiclink()
  {
      global $show, $phpname, $curgal, $curpic, $numpics, $numgals;
      
      $newpic = $curpic+1;
      if($newpic >= $numpics)
          $newpic = 0;
          
      return $phpname . '?show=' . $show . '&amp;gallery=' . $curgal .
             '&amp;pic=' . $newpic;
  }
  
  function prevpiclink()
  {
      global $show, $phpname, $curgal, $curpic, $numpics;
      
      $newpic = $curpic-1;
      if($newpic < 0)
          $newpic = $numpics-1;
          
      return $phpname . '?show=' . $show . '&amp;gallery=' . $curgal .
             '&amp;pic=' . $newpic;
  }
  
  function nextgallink()
  {
      global $show, $phpname, $curgal, $numgals;
      
      $newgal = $curgal+1;
      if($newgal >= $numgals)
          $newgal = 0;

      return $phpname . '?show=' . $show . '&amp;gallery=' . $newgal;
  }
  
  function prevgallink()
  {
      global $show, $phpname, $curgal, $numgals;
      
      $newgal = $curgal-1;
      if($newgal < 0)
          $newgal = $numgals-1;
      
      return $phpname . '?show=' . $show . '&amp;gallery=' . $newgal;
  }
  
  // returns img tag for the pic, with $imgopt
  function thepic($imgopt = "")
  {
      global $show, $gals, $pics, $curgal, $curpic;
      global $fprefix;
      
      if($show == "video") {
          return '<iframe width="560" height="315" src="//www.youtube.com/embed/' . $pics[$curpic][1] . '" frameborder="0" allowfullscreen></iframe>';
      } else {
          return '<img src="' . $fprefix . fname($gals[$curgal][0]) . '/' . fname($pics[$curpic][0]) . '.' . fext($pics[$curpic][0]) . '" ' .
                 'width="' . $pics[$curpic][1] . '" height="' . $pics[$curpic][2] . '" ' . $imgopt . '>';
      }
             
  }
  
?>