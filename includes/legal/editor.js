(function(wp){
    'use strict';
    var el=wp.element.createElement;
    ['erecht24/erecht24','erecht24/legal-text'].forEach(function(name){
        if(wp.blocks.getBlockType(name)){return;}
        wp.blocks.registerBlockType(name,{
            title:'Rechtstext',icon:'media-text',category:'widgets',
            attributes:{type:{type:'string',default:'imprint'},lang:{type:'string',default:'de'},strip_title:{type:'boolean',default:false}},
            edit:function(props){
                return el(wp.element.Fragment,null,
                    el(wp.blockEditor.InspectorControls,null,el(wp.components.PanelBody,{title:'Rechtstext'},
                        el(wp.components.SelectControl,{label:'Text',value:props.attributes.type,options:[{label:'Impressum',value:'imprint'},{label:'Datenschutz',value:'privacy_policy'},{label:'Social Media',value:'social_media'},{label:'AGB',value:'terms'},{label:'Widerrufsbelehrung',value:'withdrawal'}],onChange:function(v){props.setAttributes({type:v});}}),
                        el(wp.components.SelectControl,{label:'Sprache',value:props.attributes.lang,options:[{label:'Deutsch',value:'de'},{label:'Englisch',value:'en'}],onChange:function(v){props.setAttributes({lang:v});}}),
                        el(wp.components.ToggleControl,{label:'Titel ausblenden',checked:props.attributes.strip_title,onChange:function(v){props.setAttributes({strip_title:v});}})
                    )),el(wp.serverSideRender,{block:name,attributes:props.attributes}));
            },save:function(){return null;}
        });
    });
})(window.wp);
