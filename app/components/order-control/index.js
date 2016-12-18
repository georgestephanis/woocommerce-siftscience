import React, { PropTypes } from 'react';
import settings from '../../lib/settings';

const divStyle = {
	width: '24px',
	display: 'block',
	float: 'none',
};

const Icon = ( { imgUrl, alt, onClick, title } ) => {
	return (
		<div title={ title } id="siftsci_icon" className="siftsci_icon" style={ divStyle } onClick={ onClick } >
			<img src={ imgUrl } alt={ alt } width="20px" height="20px" />
		</div>
	);
};

const scoreStyle = {
	color: 'white',
	textAlign: 'center',
	border: '1px solid black',
	width: '20px',
	height: '20px',
	margin: '0px',
};

const getColor = ( score ) => {
	const thresholdBad = settings.thresholdBad || 60;
	if ( thresholdBad <= score ) {
		return 'red';
	}

	const thresholdGood = settings.thresholdGood || 30;
	if ( thresholdGood >= score ) {
		return 'green';
	}

	return 'orange';
};

const Score = ( { score, openSiftSci } ) => {
	const style = Object.assign( {}, scoreStyle, {
		backgroundColor: getColor( score ),
	} );

	const title = "The user's SiftScience score";

	return (
		<div title={ title } id="siftsci_score" className="siftsci_score" style={ divStyle } onClick={ openSiftSci }>
			<div style={ style }>{ score }</div>
		</div>
	);
};

const LabelButton = ( { type, label, imgPath, setLabel } ) => {
	const isSet = type === label;
	const title = isSet ? 'Click to remove this label' : 'Click to set this label';
	const image = type + ( isSet ? '.png' : '-gray.png' );
	const callback = () => setLabel( isSet ? null : type );
	return (
		<Icon title={ title } imgUrl={ imgPath + image } alt={ type } onClick={ callback } />
	);
};

const control = ( props ) => {
	const { error, isWorking, isBackfilled, score, imgPath, uploadOrder } = props;
	if ( error ) {
		console.log( 'error: ', error );
		return <Icon imgUrl={ imgPath + 'error.png' } alt="error" />;
	}

	if ( isWorking ) {
		return <Icon imgUrl={ imgPath + 'spinner.gif' } alt="working" />;
	}

	if ( isBackfilled && score ) {
		return (
			<div>
				<Score { ...props } />
				<LabelButton { ...props } type="good" />
				<LabelButton { ...props } type="bad" />
			</div>
		);
	}

	return <Icon imgUrl={ imgPath + 'upload.png' } alt="upload" onClick={ uploadOrder } />;
};

control.propTypes = {
	error: PropTypes.string,
	isWorking: PropTypes.bool.isRequired,
	isBackfilled: PropTypes.bool,
	score: PropTypes.number,
	label: PropTypes.string,
	imgPath: PropTypes.string.isRequired,
	openSiftSci: PropTypes.func.isRequired,
	setLabel: PropTypes.func.isRequired,
	uploadOrder: PropTypes.func.isRequired,
};

export default control;
