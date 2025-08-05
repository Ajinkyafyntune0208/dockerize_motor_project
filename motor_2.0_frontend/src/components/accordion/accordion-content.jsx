import React from 'react'
import styled from 'styled-components';

const Wrapper = styled.div`
`;

const AccordionContent = props => {
    const { children } = props;

    return (
        <Wrapper>
            {children}
        </Wrapper>
    )
};

export default AccordionContent;
