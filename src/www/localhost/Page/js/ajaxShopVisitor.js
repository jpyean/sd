function uVisitor(basePath,shopId){
		var ud= $.cookie('_ud'+shopId); 
		$.ajax({
					url : basePath+'Shop_ajaxShopVisitor',
					dataType : "JSON",
					data : {
						shopId : shopId,
						ud : ''
					},
					async : true,
					cache : false,
					type  : "POST",
					success : function(resData){
						$.cookie('_ud'+shopId, { path: "/"},Math.random());
					}
		}); 
}